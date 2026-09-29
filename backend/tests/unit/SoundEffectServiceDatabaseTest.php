<?php

use App\Services\Audio\SoundEffectService;
use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGuardService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class SoundEffectServiceDatabaseTest extends CIUnitTestCase
{
    private BaseConnection $effectsDb;
    private SoundEffectService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->effectsDb = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $this->createSchema();
        $this->seedCatalog();
        $guard = new class extends CampaignGuardService {
            public function __construct()
            {
            }

            public function context(array $auth, int $campaignId): array
            {
                if ($campaignId !== 7 || !in_array((int) ($auth['user_id'] ?? 0), [1, 2], true)) {
                    throw new CampaignException('forbidden', 'Campaign is outside your access scope.', 403);
                }
                $isGameMaster = (int) $auth['user_id'] === 1;
                return [
                    'auth' => $auth,
                    'campaign' => ['id' => 7, 'game_master_id' => 1, 'rpg_system_id' => 4, 'rpg_universe_id' => 9],
                    'isGameMaster' => $isGameMaster,
                    'isAdmin' => false,
                ];
            }
        };
        $this->service = new SoundEffectService($this->effectsDb, $guard);
    }

    protected function tearDown(): void
    {
        $this->effectsDb->close();
        parent::tearDown();
    }

    public function testPersistsScreenLifecycleAndSlotAssignments(): void
    {
        $initial = $this->service->snapshot($this->gm(), 7);
        $this->assertSame(['A', 'B', 'C', 'D'], array_column($initial['screens'], 'label'));

        $created = $this->service->createScreen($this->gm(), 7, ['name' => 'Boss fight']);
        $screenId = (int) $created['screen']['id'];
        $this->assertSame('E', $created['screen']['label']);

        $this->service->updateScreen($this->gm(), 7, $screenId, [
            'position' => 0,
            'name' => 'Finale',
            'columns' => 20,
            'rows' => 10,
            'textLines' => 5,
            'padStyle' => 'wide',
        ]);
        $moved = $this->service->snapshot($this->gm(), 7);
        $this->assertSame($screenId, $moved['screens'][0]['id']);
        $this->assertSame('Finale', $moved['screens'][0]['name']);
        $this->assertSame(20, $moved['screens'][0]['columns']);
        $this->assertSame(10, $moved['screens'][0]['rows']);
        $this->assertSame(5, $moved['screens'][0]['textLines']);
        $this->assertSame('wide', $moved['screens'][0]['padStyle']);

        $saved = $this->service->saveSlot($this->gm(), 7, $screenId, 199, [
            'audioTrackId' => 31,
            'name' => 'Dragon roar',
            'icon' => 'monster',
            'color' => '#cc8844',
            'shortcut' => 'SHIFT+1',
            'volume' => 0.65,
            'loop' => true,
            'fadeInMs' => 250,
            'fadeOutMs' => 500,
            'stopOthers' => true,
            'audienceScope' => 'selected',
            'recipientUserIds' => [2],
        ]);
        $this->assertSame(199, $saved['slot']['position']);
        $this->assertSame(31, $saved['slot']['audioTrackId']);
        $this->assertTrue($saved['slot']['loop']);

        $freshService = new SoundEffectService($this->effectsDb, $this->guard());
        $persisted = $freshService->snapshot($this->gm(), 7);
        $persistedScreen = array_values(array_filter(
            $persisted['screens'],
            static fn (array $screen): bool => (int) $screen['id'] === $screenId
        ))[0];
        $this->assertSame('Dragon roar', $persistedScreen['slots'][0]['name']);
        $this->assertSame([2], $persistedScreen['slots'][0]['recipientUserIds']);

        $this->service->updateScreen($this->gm(), 7, $screenId, [
            'columns' => 1, 'rows' => 1, 'textLines' => 1, 'padStyle' => 'compact',
        ]);
        $shrunk = $this->service->snapshot($this->gm(), 7);
        $shrunkScreen = array_values(array_filter(
            $shrunk['screens'],
            static fn (array $screen): bool => (int) $screen['id'] === $screenId
        ))[0];
        $this->assertSame(199, $shrunkScreen['slots'][0]['position']);
        $this->assertSame(1, $shrunkScreen['columns']);

        $this->service->deleteSlot($this->gm(), 7, $screenId, 199);
        $this->service->deleteScreen($this->gm(), 7, $screenId);
        $afterDelete = $this->service->snapshot($this->gm(), 7);
        $this->assertCount(4, $afterDelete['screens']);
        $this->assertSame([0, 1, 2, 3], array_column($afterDelete['screens'], 'sortOrder'));
    }

    public function testPlayersCanReadButCannotManageOrControlEffects(): void
    {
        $snapshot = $this->service->snapshot($this->player(), 7);
        $this->assertFalse($snapshot['capabilities']['canManage']);
        $this->assertFalse($snapshot['capabilities']['canControl']);

        foreach (['screen', 'command'] as $operation) {
            try {
                if ($operation === 'screen') {
                    $this->service->createScreen($this->player(), 7);
                } else {
                    $this->service->command($this->player(), 7, ['type' => 'SOUND_EFFECT_STOP_ALL']);
                }
                $this->fail('A player operation should have been rejected.');
            } catch (CampaignException $exception) {
                $this->assertSame(403, $exception->status());
            }
        }
    }

    public function testRejectsUnexpectedAndOutOfRangeSlotSettings(): void
    {
        $screenId = (int) $this->service->snapshot($this->gm(), 7)['screens'][0]['id'];
        foreach ([
            ['audioTrackId' => 31, 'name' => 'Invalid', 'volume' => 1.1],
            ['audioTrackId' => 31, 'name' => 'Invalid', 'loop' => 'yes'],
            ['audioTrackId' => 31, 'name' => 'Invalid', 'injected' => true],
        ] as $payload) {
            try {
                $this->service->saveSlot($this->gm(), 7, $screenId, 0, $payload);
                $this->fail('Invalid slot settings should have been rejected.');
            } catch (CampaignException $exception) {
                $this->assertSame(422, $exception->status());
            }
        }
        $this->assertSame([], $this->service->snapshot($this->gm(), 7)['screens'][0]['slots']);
    }

    public function testValidatesAndDuplicatesPerScreenLayout(): void
    {
        $source = $this->service->snapshot($this->gm(), 7)['screens'][0];
        $this->service->updateScreen($this->gm(), 7, (int) $source['id'], [
            'columns' => 8, 'rows' => 6, 'textLines' => 3, 'padStyle' => 'compact',
        ]);

        $duplicate = $this->service->duplicateScreen($this->gm(), 7, (int) $source['id']);
        $this->assertSame(8, $duplicate['screen']['columns']);
        $this->assertSame(6, $duplicate['screen']['rows']);
        $this->assertSame(3, $duplicate['screen']['textLines']);
        $this->assertSame('compact', $duplicate['screen']['padStyle']);

        foreach ([
            ['columns' => 0],
            ['columns' => 21],
            ['rows' => 11],
            ['textLines' => 6],
            ['padStyle' => 'circle'],
        ] as $payload) {
            try {
                $this->service->updateScreen($this->gm(), 7, (int) $source['id'], $payload);
                $this->fail('Invalid screen layout should have been rejected.');
            } catch (CampaignException $exception) {
                $this->assertSame(422, $exception->status());
            }
        }
    }

    public function testMigrationAndRoutesKeepAssignmentsBoundToExistingAudio(): void
    {
        $migration = file_get_contents(APPPATH . 'Database/Migrations/2026-09-14-100000_CreateSoundEffects.php');
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertStringContainsString("addForeignKey('audio_track_id', 'audio_tracks', 'id'", $migration);
        $this->assertStringContainsString("addUniqueKey(['screen_id', 'slot_position'])", $migration);
        $this->assertStringContainsString('sound-effects/screens/(:num)/slots/(:num)', $routes);
        $this->assertStringContainsString('internal/realtime/campaigns/(:num)/sound-effects/command', $routes);
    }

    private function gm(): array
    {
        return ['user_id' => 1, 'role' => 'user'];
    }

    private function player(): array
    {
        return ['user_id' => 2, 'role' => 'user'];
    }

    private function guard(): CampaignGuardService
    {
        return new class extends CampaignGuardService {
            public function __construct()
            {
            }

            public function context(array $auth, int $campaignId): array
            {
                return [
                    'auth' => $auth,
                    'campaign' => ['id' => $campaignId, 'game_master_id' => 1, 'rpg_system_id' => 4, 'rpg_universe_id' => 9],
                    'isGameMaster' => (int) ($auth['user_id'] ?? 0) === 1,
                    'isAdmin' => false,
                ];
            }
        };
    }

    private function createSchema(): void
    {
        $statements = [
            'CREATE TABLE campaigns (id INTEGER PRIMARY KEY, game_master_id INTEGER, rpg_system_id INTEGER, rpg_universe_id INTEGER)',
            'CREATE TABLE campaign_members (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, user_id INTEGER, is_active INTEGER)',
            'CREATE TABLE audio_libraries (id INTEGER PRIMARY KEY, name TEXT, scope TEXT, owner_user_id INTEGER, system_id INTEGER, setting_id INTEGER, is_active INTEGER)',
            'CREATE TABLE audio_tracks (id INTEGER PRIMARY KEY, library_id INTEGER, owner_user_id INTEGER, title TEXT, category TEXT, source_type TEXT, external_url TEXT, storage_key TEXT, mime_type TEXT, duration_seconds REAL, status TEXT, deleted_at TEXT)',
            'CREATE TABLE campaign_audio_tracks (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, audio_track_id INTEGER, added_by_user_id INTEGER, is_enabled INTEGER, sort_order INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE campaign_sound_effect_settings (campaign_id INTEGER PRIMARY KEY, revision INTEGER, updated_by_user_id INTEGER, created_at TEXT, updated_at TEXT)',
            "CREATE TABLE sound_effect_screens (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, label TEXT, name TEXT, sort_order INTEGER, grid_columns INTEGER DEFAULT 3, grid_rows INTEGER DEFAULT 4, text_lines INTEGER DEFAULT 1, pad_style TEXT DEFAULT 'square', created_by_user_id INTEGER, updated_by_user_id INTEGER, created_at TEXT, updated_at TEXT)",
            'CREATE TABLE sound_effect_slots (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, screen_id INTEGER, slot_position INTEGER, audio_track_id INTEGER, name TEXT, icon TEXT, color TEXT, shortcut TEXT, volume REAL, play_mode TEXT, loop_enabled INTEGER, fade_in_ms INTEGER, fade_out_ms INTEGER, stop_others INTEGER, audience_scope TEXT, recipient_user_ids_json TEXT, created_by_user_id INTEGER, updated_by_user_id INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE sound_effect_playbacks (playback_id TEXT PRIMARY KEY, campaign_id INTEGER, slot_id INTEGER, audio_track_id INTEGER, started_at_ms INTEGER, execute_at_ms INTEGER, duration_seconds REAL, volume REAL, loop_enabled INTEGER, fade_in_ms INTEGER, fade_out_ms INTEGER, audience_scope TEXT, recipient_user_ids_json TEXT, created_by_user_id INTEGER, stopped_at_ms INTEGER, created_at TEXT, updated_at TEXT)',
        ];
        foreach ($statements as $statement) {
            $this->effectsDb->query($statement);
        }
    }

    private function seedCatalog(): void
    {
        $this->effectsDb->table('campaigns')->insert(['id' => 7, 'game_master_id' => 1, 'rpg_system_id' => 4, 'rpg_universe_id' => 9]);
        $this->effectsDb->table('campaign_members')->insert(['campaign_id' => 7, 'user_id' => 2, 'is_active' => 1]);
        $this->effectsDb->table('audio_libraries')->insert([
            'id' => 21, 'name' => 'Setting', 'scope' => 'system', 'system_id' => 4, 'setting_id' => 9, 'is_active' => 1,
        ]);
        $this->effectsDb->table('audio_tracks')->insert([
            'id' => 31, 'library_id' => 21, 'title' => 'Dragon', 'category' => 'sfx', 'source_type' => 'upload',
            'storage_key' => 'audio/dragon.ogg', 'mime_type' => 'audio/ogg', 'duration_seconds' => 8.5,
            'status' => 'ready', 'deleted_at' => null,
        ]);
    }
}
