<?php

use App\Services\Token\TokenSyncException;
use App\Services\Token\TokenSyncService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/** @internal */
final class TokenSyncServiceDatabaseTest extends CIUnitTestCase
{
    private BaseConnection $syncDb;
    private TokenSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncDb = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $this->createSchema();
        $this->seed();
        $this->service = new TokenSyncService($this->syncDb);
    }

    protected function tearDown(): void
    {
        $this->syncDb->close();
        parent::tearDown();
    }

    public function testExposesOnlyTheApprovedNonGeometryProjection(): void
    {
        $fields = TokenSyncService::apiFields();
        foreach (['name', 'imageUrl', 'statuses', 'resources', 'vision', 'movementSpent'] as $field) {
            $this->assertContains($field, $fields);
        }
        foreach (['id', 'characterId', 'x', 'y', 'width', 'height', 'rotation',
            'facing', 'elevation', 'movementPoints', 'revision'] as $field) {
            $this->assertNotContains($field, $fields);
        }
    }

    public function testOnlyCampaignGameMasterCanReadOrWriteSynchronization(): void
    {
        $catalog = $this->service->catalog(7, $this->gm());
        $this->assertCount(3, $catalog['scenes']);
        $this->assertCount(5, $catalog['tokens']);

        $coGmCatalog = $this->service->catalog(7, ['user_id' => 3]);
        $this->assertTrue($coGmCatalog['capabilities']['canManage']);

        try {
            $this->service->catalog(7, ['user_id' => 2]);
            $this->fail('A player must not read synchronization metadata.');
        } catch (TokenSyncException $exception) {
            $this->assertSame(403, $exception->status());
        }
    }

    public function testPreviewAndAtomicTransferCopyStateButKeepGeometryAndIdentity(): void
    {
        $preview = $this->service->preview(7, $this->gm(), [
            'sourceTokenId' => 10,
            'targetTokenIds' => [11, 12],
        ]);
        $this->assertSame(3, $preview['sourceRevision']);
        $this->assertSame([11, 12], array_column($preview['targets'], 'tokenId'));
        $this->assertContains('name', array_column($preview['targets'][0]['changes'], 'field'));
        $this->assertNotContains('x', array_column($preview['targets'][0]['changes'], 'field'));

        $result = $this->service->transfer(7, $this->gm(), [
            'sourceTokenId' => 10,
            'sourceRevision' => 3,
            'targets' => [
                ['tokenId' => 11, 'revision' => 4],
                ['tokenId' => 12, 'revision' => 5],
            ],
        ]);
        $this->assertCount(2, $result['synchronizedTokens']);
        $source = $this->row(10);
        $target = $this->row(11);
        foreach (TokenSyncService::databaseFields() as $field) {
            $this->assertEquals($source[$field], $target[$field], $field);
        }
        $this->assertSame('Hero source', $target['name']);
        $this->assertSame(77.0, (float) $target['x']);
        $this->assertSame(125.0, (float) $target['width']);
        $this->assertSame(180.0, (float) $target['rotation']);
        $this->assertSame(42, (int) $target['character_id']);
        $this->assertSame(9.0, (float) $target['movement_range']);
        $this->assertSame(2.0, (float) $target['movement_spent']);
        $this->assertSame(['poisoned'], json_decode($target['statuses_json'], true));
        $this->assertSame('Hero source', $this->row(12)['name']);
        $this->assertSame(88.0, (float) $this->row(12)['x']);
        $this->assertSame('Other hero', $this->row(13)['name']);
    }

    public function testRevisionConflictRollsBackEveryTarget(): void
    {
        try {
            $this->service->transfer(7, $this->gm(), [
                'sourceTokenId' => 10,
                'sourceRevision' => 3,
                'targets' => [
                    ['tokenId' => 11, 'revision' => 4],
                    ['tokenId' => 12, 'revision' => 999],
                ],
            ]);
            $this->fail('A stale target must abort the complete transfer.');
        } catch (TokenSyncException $exception) {
            $this->assertSame(409, $exception->status());
            $this->assertSame('Target eleven', $this->row(11)['name']);
            $this->assertSame(4, (int) $this->row(11)['revision']);
        }
    }

    public function testLiveLinksTrackDivergencePropagateChangedFieldsAndCanPause(): void
    {
        $created = $this->service->createLinks(7, $this->gm(), [
            'sourceTokenId' => 10,
            'sourceRevision' => 3,
            'targets' => [['tokenId' => 11, 'revision' => 4]],
        ]);
        $link = $created['links'][0];

        $this->syncDb->table('scene_tokens')->where('id', 11)->update([
            'hidden' => 1,
            'revision' => 6,
        ]);
        $this->service->afterTokenUpdate(7, 11, ['hidden' => 1], $this->gm());
        $catalog = $this->service->catalog(7, $this->gm());
        $this->assertSame(['hidden'], $catalog['links'][0]['divergedFields']);

        $this->syncDb->table('scene_tokens')->where('id', 10)->update([
            'name' => 'Renamed at source',
            'revision' => 4,
        ]);
        $this->service->afterTokenUpdate(7, 10, ['name' => 'Renamed at source'], $this->gm());
        $this->assertSame('Renamed at source', $this->row(11)['name']);
        $this->assertSame(1, (int) $this->row(11)['hidden']);
        $this->assertSame(['hidden'], $this->service->catalog(7, $this->gm())['links'][0]['divergedFields']);

        $this->syncDb->table('scene_tokens')->where('id', 10)->update([
            'hidden' => 0,
            'revision' => 5,
        ]);
        $this->service->afterTokenUpdate(7, 10, ['hidden' => 0], $this->gm());
        $this->assertSame([], $this->service->catalog(7, $this->gm())['links'][0]['divergedFields']);

        $this->service->updateLink(7, (int) $link['id'], $this->gm(), ['enabled' => false]);
        $this->syncDb->table('scene_tokens')->where('id', 10)->update([
            'name' => 'Paused source',
            'revision' => 6,
        ]);
        $this->service->afterTokenUpdate(7, 10, ['name' => 'Paused source'], $this->gm());
        $this->assertSame('Renamed at source', $this->row(11)['name']);

        $this->service->applyLink(7, (int) $link['id'], $this->gm());
        $this->assertSame('Paused source', $this->row(11)['name']);
        $this->assertSame([], $this->service->catalog(7, $this->gm())['links'][0]['divergedFields']);
    }

    public function testRejectsOtherCharactersSameScenesAndLinkChains(): void
    {
        foreach ([13, 14] as $targetId) {
            try {
                $this->service->preview(7, $this->gm(), [
                    'sourceTokenId' => 10,
                    'targetTokenIds' => [$targetId],
                ]);
                $this->fail('An incompatible target must be rejected.');
            } catch (TokenSyncException $exception) {
                $this->assertSame(422, $exception->status());
            }
        }

        $this->service->createLinks(7, $this->gm(), [
            'sourceTokenId' => 10,
            'sourceRevision' => 3,
            'targets' => [['tokenId' => 11, 'revision' => 4]],
        ]);
        $this->expectException(TokenSyncException::class);
        $this->service->createLinks(7, $this->gm(), [
            'sourceTokenId' => 11,
            'sourceRevision' => 5,
            'targets' => [['tokenId' => 12, 'revision' => 5]],
        ]);
    }

    public function testDeletingTokenLinksNeverDeletesEitherToken(): void
    {
        $this->service->createLinks(7, $this->gm(), [
            'sourceTokenId' => 10,
            'sourceRevision' => 3,
            'targets' => [['tokenId' => 11, 'revision' => 4]],
        ]);

        $this->service->deleteTokenLinks(7, 11);

        $this->assertSame([], $this->service->catalog(7, $this->gm())['links']);
        $this->assertSame('Hero source', $this->row(10)['name']);
        $this->assertSame('Hero source', $this->row(11)['name']);
    }

    private function gm(): array
    {
        return ['user_id' => 1, 'role' => 'user'];
    }

    private function row(int $id): array
    {
        return $this->syncDb->table('scene_tokens')->where('id', $id)->get()->getRowArray();
    }

    private function createSchema(): void
    {
        foreach ([
            'CREATE TABLE campaigns (id INTEGER PRIMARY KEY, game_master_id INTEGER)',
            'CREATE TABLE users (id INTEGER PRIMARY KEY)',
            'CREATE TABLE campaign_members (id INTEGER PRIMARY KEY, campaign_id INTEGER, user_id INTEGER, role TEXT, is_active INTEGER)',
            'CREATE TABLE scenes (id INTEGER PRIMARY KEY, campaign_id INTEGER, name TEXT, sort_order INTEGER, is_visible INTEGER, deleted_at TEXT)',
            'CREATE TABLE scene_tokens (id INTEGER PRIMARY KEY, campaign_id INTEGER, scene_id INTEGER, character_id INTEGER, name TEXT, image_url TEXT, x REAL, y REAL, width REAL, height REAL, rotation REAL, facing REAL, elevation REAL, disposition TEXT, hidden INTEGER, locked INTEGER, rotation_handle_enabled INTEGER, facing_handle_enabled INTEGER, rotation_follows_facing INTEGER, show_info_unselected INTEGER, resource_bar_position TEXT, movement_range REAL, movement_spent REAL, movement_reset_mode TEXT, visible_to_json TEXT, controlled_by_json TEXT, editable_by_json TEXT, observer_by_json TEXT, statuses_json TEXT, bars_json TEXT, vision_json TEXT, light_json TEXT, revision INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)',
            'CREATE TABLE scene_token_sync_links (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, source_scene_id INTEGER, source_token_id INTEGER, target_scene_id INTEGER, target_token_id INTEGER, enabled INTEGER, diverged_fields_json TEXT, last_synced_at TEXT, created_by INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE UNIQUE INDEX scene_token_sync_target ON scene_token_sync_links(campaign_id, target_token_id)',
            'CREATE UNIQUE INDEX scene_token_sync_pair ON scene_token_sync_links(campaign_id, source_token_id, target_token_id)',
        ] as $statement) {
            $this->syncDb->query($statement);
        }
    }

    private function seed(): void
    {
        $this->syncDb->table('campaigns')->insert(['id' => 7, 'game_master_id' => 1]);
        foreach ([1, 2, 3] as $userId) {
            $this->syncDb->table('users')->insert(['id' => $userId]);
        }
        $this->syncDb->table('campaign_members')->insertBatch([
            ['id' => 1, 'campaign_id' => 7, 'user_id' => 2, 'role' => 'player', 'is_active' => 1],
            ['id' => 2, 'campaign_id' => 7, 'user_id' => 3, 'role' => 'gm', 'is_active' => 1],
        ]);
        foreach ([[1, 'Forest'], [2, 'Dungeon'], [3, 'Tower']] as [$id, $name]) {
            $this->syncDb->table('scenes')->insert([
                'id' => $id, 'campaign_id' => 7, 'name' => $name,
                'sort_order' => $id, 'is_visible' => $id === 3 ? 0 : 1,
                'deleted_at' => null,
            ]);
        }
        $base = [
            'campaign_id' => 7, 'character_id' => 42, 'image_url' => '/source.png',
            'x' => 10, 'y' => 20, 'width' => 100, 'height' => 100,
            'rotation' => 45, 'facing' => 30, 'elevation' => 2,
            'disposition' => 'friendly', 'hidden' => 0, 'locked' => 1,
            'rotation_handle_enabled' => 1, 'facing_handle_enabled' => 1,
            'rotation_follows_facing' => 0, 'show_info_unselected' => 1,
            'resource_bar_position' => 'above', 'movement_range' => 9,
            'movement_spent' => 2, 'movement_reset_mode' => 'round',
            'visible_to_json' => '{"mode":"everyone","userIds":[]}',
            'controlled_by_json' => '{"mode":"inherit","userIds":[]}',
            'editable_by_json' => '{"mode":"gm","userIds":[]}',
            'observer_by_json' => '{"mode":"inherit","userIds":[]}',
            'statuses_json' => '["poisoned"]',
            'bars_json' => '{"bars":[],"bubbles":[]}',
            'vision_json' => '{"enabled":true,"range":60}',
            'light_json' => '{}', 'created_at' => '2026-09-17 10:00:00',
            'updated_at' => '2026-09-17 10:00:00', 'deleted_at' => null,
        ];
        $tokens = [
            array_merge($base, ['id' => 10, 'scene_id' => 1, 'name' => 'Hero source', 'revision' => 3]),
            array_merge($base, ['id' => 11, 'scene_id' => 2, 'name' => 'Target eleven', 'image_url' => '/target.png', 'x' => 77, 'width' => 125, 'rotation' => 180, 'movement_range' => 4, 'movement_spent' => 1, 'statuses_json' => '[]', 'revision' => 4]),
            array_merge($base, ['id' => 12, 'scene_id' => 3, 'name' => 'Target twelve', 'x' => 88, 'revision' => 5]),
            array_merge($base, ['id' => 13, 'scene_id' => 2, 'character_id' => 99, 'name' => 'Other hero', 'revision' => 2]),
            array_merge($base, ['id' => 14, 'scene_id' => 1, 'name' => 'Same scene', 'revision' => 2]),
        ];
        foreach ($tokens as $token) $this->syncDb->table('scene_tokens')->insert($token);
    }
}
