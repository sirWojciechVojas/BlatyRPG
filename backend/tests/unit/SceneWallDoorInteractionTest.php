<?php

use App\Models\SceneWallModel;
use App\Services\Scene\SceneService;
use App\Services\Wall\SceneWallService;
use App\Services\Wall\WallException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/** @internal */
final class SceneWallDoorInteractionTest extends CIUnitTestCase
{
    private BaseConnection $doorDb;
    private SceneWallService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->doorDb = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $this->createSchema();
        $this->seedScene();
        $scenes = new class extends SceneService {
            public function __construct()
            {
            }

            public function getScene(int $campaignId, int $sceneId, array $auth): array
            {
                return [
                    'scene' => ['id' => $sceneId, 'width' => 1000, 'height' => 1000],
                    'capabilities' => ['canManage' => (int) ($auth['user_id'] ?? 0) === 99],
                ];
            }
        };
        $this->service = new SceneWallService(
            $this->doorDb,
            new SceneWallModel($this->doorDb),
            $scenes
        );
    }

    protected function tearDown(): void
    {
        $this->doorDb->close();
        parent::tearDown();
    }

    public function testSecretDoorRejectsGameMasterActingThroughPlayerOwnedToken(): void
    {
        try {
            $this->service->interact(7, 4, 8, ['user_id' => 99], [
                'revision' => 1,
                'doorState' => 'open',
                'actingTokenIds' => [21],
            ]);
            $this->fail('A player-owned token must not operate a secret door.');
        } catch (WallException $exception) {
            $this->assertSame('secret_door_player_token', $exception->errorCode());
            $this->assertSame(403, $exception->status());
        }

        $wall = $this->doorDb->table('scene_walls')->where('id', 8)
            ->get()->getRowArray();
        $this->assertSame('closed', $wall['door_state']);
        $this->assertSame('1', (string) $wall['revision']);
    }

    public function testSecretDoorAllowsGameMastersOwnSelectedToken(): void
    {
        $result = $this->service->interact(7, 4, 8, ['user_id' => 99], [
            'revision' => 1,
            'doorState' => 'open',
            'actingTokenIds' => [22],
        ]);

        $this->assertSame('open', $result['wall']['doorState']);
        $this->assertSame(2, $result['wall']['revision']);
    }

    public function testGameMasterCanOpenSecretDoorSilently(): void
    {
        $result = $this->service->interact(7, 4, 8, ['user_id' => 99], [
            'revision' => 1,
            'doorState' => 'open',
            'actingTokenIds' => [22],
            'silent' => true,
        ]);

        $this->assertSame('open', $result['wall']['doorState']);
        $this->assertArrayNotHasKey('sound', $result);
    }

    public function testWindowUsesTheSameValidatedPortalInteraction(): void
    {
        $result = $this->service->interact(7, 4, 9, ['user_id' => 99], [
            'revision' => 1,
            'doorState' => 'open',
            'actingTokenIds' => [22],
        ]);

        $this->assertSame('open', $result['wall']['doorState']);
        $this->assertSame('open', $result['sound']);
    }

    private function createSchema(): void
    {
        $this->doorDb->query('CREATE TABLE characters (
            id INTEGER PRIMARY KEY, user_id INTEGER NULL
        )');
        $this->doorDb->query('CREATE TABLE scene_tokens (
            id INTEGER PRIMARY KEY, campaign_id INTEGER NOT NULL,
            scene_id INTEGER NOT NULL, character_id INTEGER NULL,
            deleted_at TEXT NULL
        )');
        $this->doorDb->query('CREATE TABLE scene_walls (
            id INTEGER PRIMARY KEY, campaign_id INTEGER NOT NULL,
            scene_id INTEGER NOT NULL, name TEXT NOT NULL, type TEXT NOT NULL,
            x1 REAL NOT NULL, y1 REAL NOT NULL, x2 REAL NOT NULL, y2 REAL NOT NULL,
            blocks_movement INTEGER NOT NULL, blocks_sight INTEGER NOT NULL,
            blocks_light INTEGER NOT NULL, blocks_sound INTEGER NOT NULL,
            wall_type TEXT NOT NULL, door_type TEXT NOT NULL,
            restriction_type TEXT NOT NULL, proximity_threshold REAL NOT NULL,
            player_operable INTEGER NOT NULL, door_state TEXT NULL,
            sound_config_json TEXT NULL, animation_config_json TEXT NULL,
            color TEXT NULL, enabled INTEGER NOT NULL, hidden INTEGER NOT NULL,
            revision INTEGER NOT NULL, created_at TEXT NULL,
            updated_at TEXT NULL, deleted_at TEXT NULL
        )');
    }

    private function seedScene(): void
    {
        $this->doorDb->table('characters')->insertBatch([
            ['id' => 7, 'user_id' => 11],
            ['id' => 8, 'user_id' => 99],
        ]);
        $this->doorDb->table('scene_tokens')->insertBatch([
            ['id' => 21, 'campaign_id' => 7, 'scene_id' => 4, 'character_id' => 7],
            ['id' => 22, 'campaign_id' => 7, 'scene_id' => 4, 'character_id' => 8],
        ]);
        $this->doorDb->table('scene_walls')->insert([
            'id' => 8,
            'campaign_id' => 7,
            'scene_id' => 4,
            'name' => 'Secret passage',
            'type' => 'secret',
            'x1' => 0,
            'y1' => 50,
            'x2' => 100,
            'y2' => 50,
            'blocks_movement' => 1,
            'blocks_sight' => 1,
            'blocks_light' => 1,
            'blocks_sound' => 1,
            'wall_type' => 'solid',
            'door_type' => 'secret',
            'restriction_type' => 'normal',
            'proximity_threshold' => 10,
            'player_operable' => 1,
            'door_state' => 'closed',
            'enabled' => 1,
            'hidden' => 0,
            'revision' => 1,
        ]);
        $this->doorDb->table('scene_walls')->insert([
            'id' => 9,
            'campaign_id' => 7,
            'scene_id' => 4,
            'name' => 'Courtyard window',
            'type' => 'window',
            'x1' => 200,
            'y1' => 50,
            'x2' => 300,
            'y2' => 50,
            'blocks_movement' => 1,
            'blocks_sight' => 0,
            'blocks_light' => 0,
            'blocks_sound' => 1,
            'wall_type' => 'solid',
            'door_type' => 'window',
            'restriction_type' => 'proximity',
            'proximity_threshold' => 10,
            'player_operable' => 1,
            'door_state' => 'closed',
            'enabled' => 1,
            'hidden' => 0,
            'revision' => 1,
        ]);
    }
}
