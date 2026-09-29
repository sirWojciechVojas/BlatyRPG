<?php

use App\Services\Scene\SceneService;
use App\Services\Wall\WallAudioProjectionService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/** @internal */
final class WallAudioProjectionServiceTest extends CIUnitTestCase
{
    private BaseConnection $audioDb;
    private WallAudioProjectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->audioDb = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $this->createSchema();
        $this->seedData();
        $scenes = new class extends SceneService {
            public function __construct()
            {
            }

            public function getScene(int $campaignId, int $sceneId, array $auth): array
            {
                return [
                    'scene' => [
                        'id' => $sceneId,
                        'grid_size' => 100,
                        'grid_distance' => 5,
                    ],
                    'capabilities' => ['canManage' => true],
                ];
            }
        };
        $this->service = new WallAudioProjectionService($this->audioDb, $scenes);
    }

    protected function tearDown(): void
    {
        $this->audioDb->close();
        parent::tearDown();
    }

    public function testProjectionGetsLouderAsTheSelectedTokenApproaches(): void
    {
        $near = $this->service->state(7, 4, ['user_id' => 99], 21);
        $far = $this->service->state(7, 4, ['user_id' => 99], 22);

        $this->assertCount(1, $near['activeLoops']);
        $this->assertCount(1, $far['activeLoops']);
        $this->assertGreaterThan(
            $far['activeLoops'][0]['volume'],
            $near['activeLoops'][0]['volume']
        );
        $this->assertGreaterThan(0, $far['activeLoops'][0]['volume']);
        $this->assertSame(
            $near['activeLoops'][0]['playbackId'],
            $far['activeLoops'][0]['playbackId']
        );
    }

    public function testProjectionContainsNoSecretWallGeometry(): void
    {
        $state = $this->service->state(7, 4, ['user_id' => 99], 21);
        $encoded = json_encode($state, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('geometry', $encoded);
        $this->assertStringNotContainsString('x1', $encoded);
        $this->assertStringNotContainsString('secret', $encoded);
        $this->assertSame('/api/campaigns/7/audio/tracks/5/file', $state['activeLoops'][0]['audio']['url']);
    }

    public function testPortalCueUsesTheSameSpatialProjection(): void
    {
        $near = $this->service->cue(7, 4, 8, 'open', ['user_id' => 99], 21);
        $outside = $this->service->cue(7, 4, 8, 'open', ['user_id' => 99], 23);

        $this->assertCount(1, $near['items']);
        $this->assertSame(1.0, $near['items'][0]['volume']);
        $this->assertSame([], $outside['items']);
    }

    private function createSchema(): void
    {
        $this->audioDb->query('CREATE TABLE scene_tokens (
            id INTEGER PRIMARY KEY, campaign_id INTEGER NOT NULL,
            scene_id INTEGER NOT NULL, x REAL NOT NULL, y REAL NOT NULL,
            width REAL NOT NULL, height REAL NOT NULL, deleted_at TEXT NULL
        )');
        $this->audioDb->query('CREATE TABLE scene_walls (
            id INTEGER PRIMARY KEY, campaign_id INTEGER NOT NULL,
            scene_id INTEGER NOT NULL, type TEXT NOT NULL,
            door_type TEXT NOT NULL, x1 REAL NOT NULL, y1 REAL NOT NULL,
            x2 REAL NOT NULL, y2 REAL NOT NULL, enabled INTEGER NOT NULL,
            sound_config_json TEXT NULL, deleted_at TEXT NULL
        )');
        $this->audioDb->query('CREATE TABLE audio_tracks (
            id INTEGER PRIMARY KEY, title TEXT NOT NULL, source_type TEXT NOT NULL,
            duration_seconds REAL NULL, storage_key TEXT NULL, status TEXT NOT NULL,
            deleted_at TEXT NULL
        )');
        $this->audioDb->query('CREATE TABLE campaign_audio_tracks (
            campaign_id INTEGER NOT NULL, audio_track_id INTEGER NOT NULL,
            is_enabled INTEGER NOT NULL
        )');
    }

    private function seedData(): void
    {
        $this->audioDb->table('scene_tokens')->insertBatch([
            ['id' => 21, 'campaign_id' => 7, 'scene_id' => 4, 'x' => 0, 'y' => 0, 'width' => 0, 'height' => 0],
            ['id' => 22, 'campaign_id' => 7, 'scene_id' => 4, 'x' => 0, 'y' => 150, 'width' => 0, 'height' => 0],
            ['id' => 23, 'campaign_id' => 7, 'scene_id' => 4, 'x' => 0, 'y' => 250, 'width' => 0, 'height' => 0],
        ]);
        $config = [
            'version' => 2,
            'rules' => [
                [
                    'id' => 'loop', 'enabled' => true, 'trigger' => 'proximityLoop',
                    'trackId' => 5, 'volume' => 1, 'fadeInMs' => 100,
                    'fadeOutMs' => 200, 'range' => 10, 'zoneCount' => 3,
                    'geometry' => ['mode' => 'points', 'points' => [0], 'offset' => 0],
                ],
                [
                    'id' => 'opening', 'enabled' => true, 'trigger' => 'open',
                    'trackId' => 5, 'volume' => 1, 'fadeInMs' => 0,
                    'fadeOutMs' => 0, 'range' => 10, 'zoneCount' => 3,
                    'geometry' => ['mode' => 'points', 'points' => [0], 'offset' => 0],
                ],
            ],
        ];
        $this->audioDb->table('scene_walls')->insert([
            'id' => 8, 'campaign_id' => 7, 'scene_id' => 4,
            'type' => 'secret', 'door_type' => 'secret',
            'x1' => 0, 'y1' => 0, 'x2' => 100, 'y2' => 0,
            'enabled' => 1,
            'sound_config_json' => json_encode($config, JSON_THROW_ON_ERROR),
        ]);
        $this->audioDb->table('audio_tracks')->insert([
            'id' => 5, 'title' => 'Door', 'source_type' => 'upload',
            'duration_seconds' => 2.5, 'storage_key' => 'audio/door.ogg',
            'status' => 'ready',
        ]);
        $this->audioDb->table('campaign_audio_tracks')->insert([
            'campaign_id' => 7, 'audio_track_id' => 5, 'is_enabled' => 1,
        ]);
    }
}
