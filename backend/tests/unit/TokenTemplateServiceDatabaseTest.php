<?php

use App\Models\CampaignMemberModel;
use App\Models\CampaignModel;
use App\Models\CampaignSceneStateModel;
use App\Models\SceneModel;
use App\Models\SceneTokenModel;
use App\Models\ResourcePermissionModel;
use App\Models\TokenTemplateAssetModel;
use App\Models\TokenTemplateModel;
use App\Models\UserModel;
use App\Services\Authorization\ResourceAccessPolicy;
use App\Services\Authorization\ResourcePermissionService;
use App\Services\Authorization\ResourceScopeService;
use App\Services\Campaign\CampaignAccessPolicy;
use App\Services\Campaign\CampaignAccessService;
use App\Services\Campaign\CampaignGuardService;
use App\Services\Fog\SceneVisibilityService;
use App\Services\Scene\SceneContentDuplicator;
use App\Services\Scene\ScenePayloadValidator;
use App\Services\Scene\SceneResourceAccessService;
use App\Services\Scene\SceneService;
use App\Services\Token\SceneTokenService;
use App\Services\Token\TokenAccessService;
use App\Services\Token\TokenException;
use App\Services\Token\TokenGridPositionService;
use App\Services\Token\TokenPayloadValidator;
use App\Services\Token\TokenSyncService;
use App\Services\Token\TokenTemplateAssetStorage;
use App\Services\Token\TokenTemplateService;
use App\Services\Wall\WallCollisionService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/** @internal */
final class TokenTemplateServiceDatabaseTest extends CIUnitTestCase
{
    private BaseConnection $templateDb;
    private string $assetDirectory;
    private TokenTemplateService $service;
    private SceneTokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templateDb = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $this->createSchema();
        $this->seed();
        $this->assetDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'campaign-token-template-assets-' . bin2hex(random_bytes(8));
        mkdir($this->assetDirectory, 0770, true);
        file_put_contents(
            $this->assetDirectory . DIRECTORY_SEPARATOR . 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png',
            'private image'
        );
        $this->service = $this->service();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->assetDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path)) unlink($path);
        }
        if (is_dir($this->assetDirectory)) rmdir($this->assetDirectory);
        $this->templateDb->close();
        parent::tearDown();
    }

    public function testOnlyTokenManagersCanBrowseAndInstantiateTemplates(): void
    {
        $this->assertCount(1, $this->service->list(7, $this->gm())['items']);
        try {
            $this->service->list(7, $this->player());
            $this->fail('A player must not browse the token template catalog.');
        } catch (TokenException $exception) {
            $this->assertSame(403, $exception->status());
        }
    }

    public function testEachPlacementCreatesAnIndependentNeutralSnapshot(): void
    {
        $first = $this->service->instantiate(7, 4, $this->gm(), [
            'templateId' => 31,
            'centerX' => 250,
            'centerY' => 250,
        ])['token'];
        $second = $this->service->instantiate(7, 4, $this->gm(), [
            'templateId' => 31,
            'centerX' => 450,
            'centerY' => 350,
        ])['token'];

        $this->assertNotSame($first['id'], $second['id']);
        $this->assertSame(100.0, $first['width']);
        $this->assertSame(75.0, $first['height']);
        $this->assertNull($first['characterId']);
        $this->assertSame(31, $first['tokenTemplateId']);
        $this->assertSame(41, $first['tokenTemplateAssetId']);
        $this->assertSame(0.0, $first['movementSpent']);
        $this->assertSame([], $first['statuses']);
        $this->assertFalse($first['hidden']);
        $this->assertFalse($first['locked']);
        $this->assertSame(
            '/api/campaigns/7/token-template-assets/41/file',
            $first['imageUrl']
        );

        $this->templateDb->table('token_templates')->where('id', 31)->update([
            'name' => 'Changed template',
            'movement_range' => 99,
        ]);
        $stored = $this->templateDb->table('scene_tokens')
            ->where('id', $first['id'])->get()->getRowArray();
        $this->assertSame('Library orc', $stored['name']);
        $this->assertSame(8.0, (float) $stored['movement_range']);
    }

    public function testDeletedTemplateCannotBePlacedButExistingInstancesRemain(): void
    {
        $token = $this->service->instantiate(7, 4, $this->gm(), [
            'templateId' => 31, 'centerX' => 250, 'centerY' => 250,
        ])['token'];
        $this->templateDb->table('token_templates')->where('id', 31)->update([
            'deleted_at' => '2026-09-18 12:00:00',
        ]);

        try {
            $this->service->instantiate(7, 4, $this->gm(), [
                'templateId' => 31, 'centerX' => 250, 'centerY' => 250,
            ]);
            $this->fail('A removed template must not be instantiated.');
        } catch (TokenException $exception) {
            $this->assertSame(409, $exception->status());
            $this->assertSame('token_template_unavailable', $exception->errorCode());
        }
        $this->assertNotNull(
            $this->templateDb->table('scene_tokens')->where('id', $token['id'])
                ->get()->getRowArray()
        );
    }

    public function testCampaignAssetRequiresActiveParticipationEvenForGlobalAdmin(): void
    {
        $this->assertFileExists($this->service->asset(7, 41, $this->player())['path']);
        foreach ([
            ['user_id' => 4, 'role' => 'user'],
            ['user_id' => 5, 'role' => 'admin'],
        ] as $auth) {
            try {
                $this->service->asset(7, 41, $auth);
                $this->fail('Only active table participants may fetch campaign token images.');
            } catch (TokenException $exception) {
                $this->assertSame(403, $exception->status());
            }
        }
    }

    public function testCharacterAssignmentSynchronizesResourcesAndRemovesOldLinks(): void
    {
        $token = $this->service->instantiate(7, 4, $this->gm(), [
            'templateId' => 31, 'centerX' => 250, 'centerY' => 250,
        ])['token'];
        $this->templateDb->table('scene_token_sync_links')->insert([
            'campaign_id' => 7,
            'source_scene_id' => 4,
            'source_token_id' => $token['id'],
            'target_scene_id' => 4,
            'target_token_id' => 999,
            'enabled' => 1,
            'diverged_fields_json' => '[]',
            'created_at' => '2026-09-18 10:00:00',
            'updated_at' => '2026-09-18 10:00:00',
        ]);
        $this->templateDb->table('scene_tokens')->where('id', $token['id'])->update([
            'editable_by_json' => '{"mode":"everyone","userIds":[]}',
        ]);
        try {
            $this->tokenService->update(7, 4, $token['id'], $this->player(), [
                'revision' => $token['revision'],
                'characterId' => 51,
            ]);
            $this->fail('Only a scene manager may assign a character.');
        } catch (TokenException $exception) {
            $this->assertSame(403, $exception->status());
        }

        $assigned = $this->tokenService->update(7, 4, $token['id'], $this->gm(), [
            'revision' => $token['revision'],
            'characterId' => 51,
        ])['token'];

        $this->assertSame(51, $assigned['characterId']);
        $this->assertSame('Library orc', $assigned['name']);
        $this->assertSame(9.0, $assigned['resources']['bars'][0]['value']);
        $this->assertSame(12.0, $assigned['resources']['bars'][0]['max']);
        $character = $this->templateDb->table('characters')->where('id', 51)
            ->get()->getRowArray();
        $characterData = json_decode($character['data'], true);
        $this->assertSame(9, $characterData['attributes']['hp']);
        $this->assertSame(12.0, (float) $characterData['attributes']['maxHp']);
        $this->assertSame(
            0,
            $this->templateDb->table('scene_token_sync_links')->countAllResults()
        );

        try {
            $this->tokenService->update(7, 4, $token['id'], $this->gm(), [
                'revision' => $token['revision'],
                'characterId' => null,
            ]);
            $this->fail('A stale assignment must be rejected.');
        } catch (TokenException $exception) {
            $this->assertSame(409, $exception->status());
        }

        $detached = $this->tokenService->update(7, 4, $token['id'], $this->gm(), [
            'revision' => $assigned['revision'],
            'characterId' => null,
        ])['token'];
        $this->assertNull($detached['characterId']);

        try {
            $this->tokenService->update(7, 4, $token['id'], $this->gm(), [
                'revision' => $detached['revision'],
                'characterId' => 52,
            ]);
            $this->fail('A character from another campaign must be rejected.');
        } catch (TokenException $exception) {
            $this->assertSame(422, $exception->status());
        }
    }

    private function service(): TokenTemplateService
    {
        $campaignAccess = new CampaignAccessService(
            new CampaignModel($this->templateDb),
            new CampaignMemberModel($this->templateDb),
            new CampaignAccessPolicy()
        );
        $permissionService = new ResourcePermissionService(
            $this->templateDb,
            new ResourcePermissionModel($this->templateDb),
            new CampaignMemberModel($this->templateDb),
            new CampaignGuardService(
                new CampaignModel($this->templateDb),
                new CampaignMemberModel($this->templateDb),
                new UserModel($this->templateDb),
                new CampaignAccessPolicy()
            ),
            new ResourceScopeService($this->templateDb),
            new ResourceAccessPolicy()
        );
        $sceneAccess = new SceneResourceAccessService($permissionService);
        $sceneService = new SceneService(
            $this->templateDb,
            new SceneModel($this->templateDb),
            new CampaignSceneStateModel($this->templateDb),
            $campaignAccess,
            new ScenePayloadValidator(),
            $sceneAccess,
            new SceneContentDuplicator($this->templateDb)
        );
        $tokenAccess = new TokenAccessService($this->templateDb);
        $this->tokenService = new SceneTokenService(
            $this->templateDb,
            new SceneTokenModel($this->templateDb),
            $sceneService,
            $sceneAccess,
            $tokenAccess,
            new TokenPayloadValidator(),
            new WallCollisionService($this->templateDb),
            new TokenGridPositionService(),
            new SceneVisibilityService($this->templateDb),
            new TokenSyncService($this->templateDb)
        );
        return new TokenTemplateService(
            $this->templateDb,
            new TokenTemplateModel($this->templateDb),
            new TokenTemplateAssetModel($this->templateDb),
            $campaignAccess,
            $sceneService,
            $sceneAccess,
            $this->tokenService,
            new TokenTemplateAssetStorage($this->assetDirectory)
        );
    }

    private function gm(): array
    {
        return ['user_id' => 1, 'role' => 'user'];
    }

    private function player(): array
    {
        return ['user_id' => 2, 'role' => 'user'];
    }

    private function createSchema(): void
    {
        foreach ([
            'CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, password_hash TEXT, role TEXT, avatar_url TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT)',
            'CREATE TABLE campaigns (id INTEGER PRIMARY KEY, game_master_id INTEGER, name TEXT, is_active INTEGER, status TEXT, settings_json TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT)',
            'CREATE TABLE campaign_members (id INTEGER PRIMARY KEY, campaign_id INTEGER, user_id INTEGER, role TEXT, permissions_json TEXT, is_active INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE campaign_scene_state (campaign_id INTEGER PRIMARY KEY, active_scene_id INTEGER, revision INTEGER, updated_by INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE scenes (id INTEGER PRIMARY KEY, campaign_id INTEGER, name TEXT, width INTEGER, height INTEGER, padding INTEGER, grid_type TEXT, grid_size INTEGER, grid_distance REAL, grid_offset_x REAL, grid_offset_y REAL, is_visible INTEGER, fog_enabled INTEGER, dynamic_vision INTEGER, sort_order INTEGER, revision INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)',
            'CREATE TABLE characters (id INTEGER PRIMARY KEY, campaign_id INTEGER, data TEXT, revision INTEGER, updated_at TEXT)',
            'CREATE TABLE scene_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, scene_id INTEGER, character_id INTEGER, token_template_id INTEGER, token_template_asset_id INTEGER, name TEXT, image_url TEXT, x REAL, y REAL, width REAL, height REAL, rotation REAL, facing REAL, elevation REAL, disposition TEXT, hidden INTEGER, locked INTEGER, rotation_handle_enabled INTEGER, facing_handle_enabled INTEGER, rotation_follows_facing INTEGER, show_info_unselected INTEGER, resource_bar_position TEXT, movement_range REAL, movement_spent REAL, movement_reset_mode TEXT, visible_to_json TEXT, controlled_by_json TEXT, editable_by_json TEXT, observer_by_json TEXT, statuses_json TEXT, bars_json TEXT, vision_json TEXT, light_json TEXT, sort_order INTEGER, revision INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)',
            'CREATE TABLE token_template_assets (id INTEGER PRIMARY KEY, storage_key TEXT, original_name TEXT, mime_type TEXT, byte_size INTEGER, width INTEGER, height INTEGER, created_by_user_id INTEGER, created_at TEXT)',
            'CREATE TABLE token_templates (id INTEGER PRIMARY KEY, name TEXT, image_url TEXT, image_asset_id INTEGER, width_cells REAL, height_cells REAL, rotation REAL, facing REAL, rotation_handle_enabled INTEGER, facing_handle_enabled INTEGER, rotation_follows_facing INTEGER, show_info_unselected INTEGER, resource_bar_position TEXT, elevation REAL, disposition TEXT, movement_range REAL, movement_reset_mode TEXT, bars_json TEXT, vision_json TEXT, revision INTEGER, created_by_user_id INTEGER, updated_by_user_id INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)',
            'CREATE TABLE scene_token_sync_links (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, source_scene_id INTEGER, source_token_id INTEGER, target_scene_id INTEGER, target_token_id INTEGER, enabled INTEGER, diverged_fields_json TEXT, last_synced_at TEXT, created_by INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE resource_permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, resource_type TEXT, resource_id INTEGER, user_id INTEGER, access_level TEXT, granted_by_user_id INTEGER, created_at TEXT, updated_at TEXT)',
        ] as $statement) {
            $this->templateDb->query($statement);
        }
    }

    private function seed(): void
    {
        $now = '2026-09-18 10:00:00';
        $this->templateDb->table('users')->insertBatch([
            ['id' => 1, 'username' => 'gm', 'email' => 'gm@example.test', 'password_hash' => 'unused', 'role' => 'user', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
            ['id' => 2, 'username' => 'player', 'email' => 'player@example.test', 'password_hash' => 'unused', 'role' => 'user', 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null],
        ]);
        $this->templateDb->table('campaigns')->insert([
            'id' => 7, 'game_master_id' => 1, 'name' => 'Old World',
            'is_active' => 1, 'status' => 'active', 'settings_json' => '{}',
            'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
        ]);
        $this->templateDb->table('campaign_members')->insertBatch([
            ['id' => 1, 'campaign_id' => 7, 'user_id' => 2, 'role' => 'player', 'permissions_json' => '{}', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'campaign_id' => 7, 'user_id' => 3, 'role' => 'gm', 'permissions_json' => '{}', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'campaign_id' => 7, 'user_id' => 4, 'role' => 'player', 'permissions_json' => '{}', 'is_active' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $this->templateDb->table('scenes')->insert([
            'id' => 4, 'campaign_id' => 7, 'name' => 'Arena',
            'width' => 1000, 'height' => 800, 'padding' => 0,
            'grid_type' => 'square', 'grid_size' => 50, 'grid_distance' => 1,
            'grid_offset_x' => 0, 'grid_offset_y' => 0, 'is_visible' => 1,
            'fog_enabled' => 0, 'dynamic_vision' => 0, 'sort_order' => 0,
            'revision' => 1, 'created_at' => $now, 'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->templateDb->table('characters')->insertBatch([
            [
                'id' => 51, 'campaign_id' => 7,
                'data' => '{"attributes":{"hp":9}}', 'revision' => 1,
                'updated_at' => $now,
            ],
            [
                'id' => 52, 'campaign_id' => 8,
                'data' => '{}', 'revision' => 1, 'updated_at' => $now,
            ],
        ]);
        $this->templateDb->table('token_template_assets')->insert([
            'id' => 41, 'storage_key' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png',
            'original_name' => 'orc.png', 'mime_type' => 'image/png',
            'byte_size' => 13, 'width' => 1, 'height' => 1,
            'created_by_user_id' => 5, 'created_at' => $now,
        ]);
        $this->templateDb->table('token_templates')->insert([
            'id' => 31, 'name' => 'Library orc', 'image_url' => null,
            'image_asset_id' => 41, 'width_cells' => 2, 'height_cells' => 1.5,
            'rotation' => 15, 'facing' => 30, 'rotation_handle_enabled' => 1,
            'facing_handle_enabled' => 1, 'rotation_follows_facing' => 0,
            'show_info_unselected' => 0, 'resource_bar_position' => 'below',
            'elevation' => 2, 'disposition' => 'hostile', 'movement_range' => 8,
            'movement_reset_mode' => 'round',
            'bars_json' => '{"bars":[{"enabled":true,"label":"HP","value":7,"max":12,"color":"#d95d55","attributePath":"attributes.hp","maxAttributePath":"attributes.maxHp","movementSource":false}],"bubbles":[]}',
            'vision_json' => '{}', 'revision' => 1, 'created_by_user_id' => 5,
            'updated_by_user_id' => 5, 'created_at' => $now, 'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
