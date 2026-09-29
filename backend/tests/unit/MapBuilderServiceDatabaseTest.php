<?php

use App\Services\Campaign\CampaignAccessService;
use App\Services\MapBuilder\MapBuilderService;
use App\Services\MapBuilder\MapBuilderException;
use App\Services\MapBuilder\MapDocumentValidator;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/** @internal */
final class MapBuilderServiceDatabaseTest extends CIUnitTestCase
{
    private BaseConnection $mapDb;
    private MapBuilderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapDb = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $this->createSchema();
        $this->seed();
        $access = $this->getMockBuilder(CampaignAccessService::class)
            ->disableOriginalConstructor()->onlyMethods(['forCampaign'])->getMock();
        $access->method('forCampaign')->willReturn([
            'exists' => true,
            'allowed' => true,
            'capabilities' => ['canManage' => true, 'canViewHidden' => true],
        ]);
        $this->service = new MapBuilderService($this->mapDb, $access);
    }

    protected function tearDown(): void
    {
        $this->mapDb->close();
        parent::tearDown();
    }

    public function testTavernAiEditReopenAndPublishPreservesSessionState(): void
    {
        $auth = ['user_id' => 2, 'session_id' => 99];
        $document = $this->tavernDocument();
        $created = $this->service->createProject(7, $auth, [
            'name' => 'Karczma pod Dębem',
            'sceneId' => 4,
            'document' => $document,
            'isTemplate' => true,
            'editorId' => 'editor-test-0001',
        ]);
        $mapId = $created['map']['id'];
        $this->assertSame(1, $created['revision']['number']);
        $this->assertTrue($created['map']['isTemplate']);

        $proposal = [
            'summary' => 'Wyposażenie izby wspólnej',
            'removeObjectIds' => [],
            'objects' => [
                $this->asset('ai-table', 'starter.tavern-table', 1100, 900, 240, 160),
                $this->asset('ai-chair-1', 'starter.chair', 900, 900, 75, 75),
                $this->asset('ai-chair-2', 'starter.chair', 1300, 900, 75, 75),
            ],
            'warnings' => [],
        ];
        $validation = (new MapDocumentValidator())->validateAiProposal($proposal, $document);
        $this->assertTrue($validation['valid'], json_encode($validation['errors']));
        $document['objects'] = array_merge($document['objects'], $proposal['objects']);
        $saved = $this->service->saveProject(7, $mapId, $auth, [
            'name' => 'Karczma pod Dębem',
            'sceneId' => 4,
            'baseRevision' => 1,
            'document' => $document,
            'isTemplate' => true,
            'editorId' => 'editor-test-0001',
        ]);
        $this->assertSame(2, $saved['revision']['number']);

        foreach ($document['objects'] as &$object) {
            if ($object['id'] === 'ai-table') {
                $object['x'] = 1200;
                $object['rotation'] = 90;
            }
        }
        unset($object);
        $saved = $this->service->saveProject(7, $mapId, $auth, [
            'name' => 'Karczma pod Dębem',
            'sceneId' => 4,
            'baseRevision' => 2,
            'document' => $document,
            'isTemplate' => true,
            'editorId' => 'editor-test-0001',
        ]);
        $this->assertSame(3, $saved['revision']['number']);

        $reopened = $this->service->getProject(7, $mapId, $auth);
        $table = array_values(array_filter(
            $reopened['revision']['document']['objects'],
            static fn (array $object): bool => $object['id'] === 'ai-table'
        ))[0];
        $this->assertSame(1200, $table['x']);
        $this->assertSame(90, $table['rotation']);

        $published = $this->service->publish(7, $mapId, $auth, [
            'revision' => 3,
            'sceneId' => 4,
            'renderAssetUrl' => '/api/campaigns/7/scene-assets/'
                . str_repeat('a', 32) . '.webp/file',
            'editorId' => 'editor-test-0001',
        ]);
        $this->assertTrue($published['published']);
        $this->assertGreaterThanOrEqual(5, $published['wallCount']);
        $this->assertSame(1, $published['lightCount']);
        $this->assertSame(['tokens' => true, 'fog' => true], $published['preserved']);
        $this->assertSame(1, $this->mapDb->table('scene_tokens')->countAllResults());
        $this->assertSame(1, $this->mapDb->table('scene_fog_states')->countAllResults());
        $this->assertSame(
            1,
            $this->mapDb->table('scene_walls')->where('door_type', 'door')->countAllResults()
        );
        $scene = $this->mapDb->table('scenes')->where('id', 4)->get()->getRowArray();
        $this->assertSame(2, (int) $scene['revision']);
        $this->assertStringContainsString('/scene-assets/', $scene['background_url']);
    }

    public function testSameSessionDifferentEditorCannotTakeLiveLock(): void
    {
        $created = $this->service->createProject(7, ['user_id' => 2, 'session_id' => 99], [
            'name' => 'Projekt z blokadą',
            'sceneId' => 4,
            'document' => $this->tavernDocument(),
            'editorId' => 'editor-first-0001',
        ]);
        try {
            $this->service->acquireLock(
                7,
                $created['map']['id'],
                ['user_id' => 2, 'session_id' => 99],
                'editor-second-0002'
            );
            $this->fail('A second editor instance must not take a live project lock.');
        } catch (MapBuilderException $exception) {
            $this->assertSame(423, $exception->status());
            $this->assertSame('map_locked', $exception->errorCode());
        }
    }

    private function tavernDocument(): array
    {
        return [
            'schemaVersion' => 1,
            'coordinateSystem' => ['unit' => 'px', 'origin' => 'top-left', 'yAxis' => 'down'],
            'width' => 4000,
            'height' => 3000,
            'pixelsPerMeter' => 100,
            'backgroundColor' => '#52693c',
            'grid' => [
                'type' => 'square', 'size' => 100, 'distance' => 1, 'unit' => 'm',
                'offsetX' => 0, 'offsetY' => 0, 'color' => '#d8cab0', 'opacity' => 0.22,
                'snap' => true,
            ],
            'levels' => [['id' => 'ground', 'name' => 'Parter', 'elevation' => 0, 'visible' => true]],
            'activeLevelId' => 'ground',
            'layers' => [
                ['id' => 'terrain', 'name' => 'Teren', 'order' => 0, 'visible' => true],
                ['id' => 'rooms', 'name' => 'Pomieszczenia', 'order' => 10, 'visible' => true],
                ['id' => 'objects', 'name' => 'Obiekty', 'order' => 20, 'visible' => true],
                ['id' => 'walls', 'name' => 'Ściany', 'order' => 30, 'visible' => true],
                ['id' => 'lights', 'name' => 'Światła', 'order' => 40, 'visible' => true],
                [
                    'id' => 'gm-secrets', 'name' => 'Sekrety MG', 'order' => 50,
                    'visible' => true, 'private' => true,
                ],
            ],
            'objects' => [
                $this->object('room-main', 'room', 'rooms', 1100, 900, 1200, 800, [
                    'shape' => 'rect', 'color' => '#725035',
                ]),
                $this->object('courtyard', 'room', 'rooms', 2500, 1000, 1000, 1000, [
                    'shape' => 'rect', 'color' => '#74583f',
                ]),
                $this->object('front-door', 'door', 'walls', 1100, 1300, 150, 16, [
                    'doorState' => 'closed', 'color' => '#8b8170',
                ]),
                $this->object('hearth-light', 'light', 'lights', 700, 700, 180, 180, [
                    'color' => '#ffb35c',
                    'light' => [
                        'brightRadius' => 250, 'dimRadius' => 600,
                        'color' => '#ffb35c', 'animation' => 'torch',
                    ],
                ]),
                $this->object('gm-only-light', 'light', 'gm-secrets', 3000, 2000, 180, 180, [
                    'private' => true,
                    'light' => [
                        'brightRadius' => 200, 'dimRadius' => 500,
                        'color' => '#ff0000', 'animation' => 'none',
                    ],
                ]),
            ],
            'masks' => [],
            'private' => ['gmNotes' => []],
            'metadata' => ['createdAt' => gmdate('c'), 'updatedAt' => gmdate('c')],
        ];
    }

    private function asset(string $id, string $assetId, int $x, int $y, int $width, int $height): array
    {
        return $this->object($id, 'asset', 'objects', $x, $y, $width, $height, [
            'assetId' => $assetId,
            'rotation' => 0,
        ]);
    }

    private function object(
        string $id,
        string $type,
        string $layerId,
        int $x,
        int $y,
        int $width,
        int $height,
        array $extra = []
    ): array {
        return $extra + [
            'id' => $id, 'type' => $type, 'layerId' => $layerId, 'levelId' => 'ground',
            'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height,
            'rotation' => 0, 'scaleX' => 1, 'scaleY' => 1, 'opacity' => 1,
            'visible' => true, 'locked' => false, 'points' => [],
        ];
    }

    private function createSchema(): void
    {
        $queries = [
            'CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT)',
            'CREATE TABLE scenes (id INTEGER PRIMARY KEY, campaign_id INTEGER, background_url TEXT, width INTEGER, height INTEGER, background_color TEXT, grid_type TEXT, grid_size INTEGER, grid_distance REAL, grid_unit TEXT, grid_offset_x REAL, grid_offset_y REAL, grid_color TEXT, grid_opacity REAL, revision INTEGER, deleted_at TEXT, updated_at TEXT)',
            'CREATE TABLE map_projects (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, scene_id INTEGER, name TEXT, is_template INTEGER, current_revision INTEGER, published_revision INTEGER, published_at TEXT, lock_user_id INTEGER, lock_session_id INTEGER, lock_client_id TEXT, lock_expires_at TEXT, created_by_user_id INTEGER, updated_by_user_id INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)',
            'CREATE TABLE map_revisions (id INTEGER PRIMARY KEY AUTOINCREMENT, map_id INTEGER, revision_number INTEGER, document_json TEXT, document_bytes INTEGER, checksum_sha256 TEXT, summary TEXT, created_by_user_id INTEGER, created_at TEXT)',
            'CREATE TABLE scene_walls (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, scene_id INTEGER, source_map_id INTEGER, source_map_revision INTEGER, source_map_object_id TEXT, name TEXT, type TEXT, x1 REAL, y1 REAL, x2 REAL, y2 REAL, blocks_movement INTEGER, blocks_sight INTEGER, blocks_light INTEGER, door_state TEXT, color TEXT, enabled INTEGER, hidden INTEGER, wall_type TEXT, door_type TEXT, restriction_type TEXT, blocks_sound INTEGER, proximity_threshold REAL, player_operable INTEGER, revision INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)',
            'CREATE TABLE scene_lights (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, scene_id INTEGER, source_map_id INTEGER, source_map_revision INTEGER, source_map_object_id TEXT, x REAL, y REAL, bright_radius REAL, dim_radius REAL, color TEXT, intensity REAL, opacity REAL, softness REAL, clarity REAL, gradual_illumination INTEGER, darkness_min REAL, darkness_max REAL, source_type TEXT, provides_vision INTEGER, constrained_by_walls INTEGER, animation TEXT, animation_speed REAL, animation_intensity REAL, elevation REAL, enabled INTEGER, hidden INTEGER, name TEXT, lumens INTEGER, direction REAL, angle REAL, area_width REAL, area_height REAL, animation_reverse INTEGER, brightness REAL, saturation REAL, contrast REAL, edge_softness REAL, transition_ratio REAL, revision INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)',
            'CREATE TABLE scene_tiles (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, scene_id INTEGER, source_map_id INTEGER, source_map_revision INTEGER, source_map_object_id TEXT, updated_at TEXT, deleted_at TEXT)',
            'CREATE TABLE scene_tokens (id INTEGER PRIMARY KEY, campaign_id INTEGER, scene_id INTEGER, name TEXT)',
            'CREATE TABLE scene_fog_states (id INTEGER PRIMARY KEY, campaign_id INTEGER, scene_id INTEGER, state_json TEXT)',
        ];
        foreach ($queries as $query) $this->mapDb->query($query);
    }

    private function seed(): void
    {
        $this->mapDb->table('users')->insert(['id' => 2, 'username' => 'GM']);
        $this->mapDb->table('scenes')->insert([
            'id' => 4, 'campaign_id' => 7, 'background_url' => null,
            'width' => 4000, 'height' => 3000, 'background_color' => '#000000',
            'grid_type' => 'square', 'grid_size' => 100, 'grid_distance' => 1,
            'grid_unit' => 'm', 'grid_offset_x' => 0, 'grid_offset_y' => 0,
            'grid_color' => '#000000', 'grid_opacity' => 0.35, 'revision' => 1,
            'deleted_at' => null, 'updated_at' => '2026-09-18 00:00:00',
        ]);
        $this->mapDb->table('scene_tokens')->insert([
            'id' => 51, 'campaign_id' => 7, 'scene_id' => 4, 'name' => 'Bohater',
        ]);
        $this->mapDb->table('scene_fog_states')->insert([
            'id' => 61, 'campaign_id' => 7, 'scene_id' => 4, 'state_json' => '{"revealed":true}',
        ]);
    }
}
