<?php

namespace Tests\Unit;

use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGuardService;
use App\Services\Profession\ProfessionCatalogService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class ProfessionCatalogServiceDatabaseTest extends CIUnitTestCase
{
    private BaseConnection $professionsDb;
    private ProfessionCatalogService $service;
    private $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->professionsDb = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $this->createSchema();
        $this->seed();

        $guard = new class extends CampaignGuardService {
            private $canManage = false;

            public function __construct()
            {
            }

            public function setCanManage(bool $canManage): void
            {
                $this->canManage = $canManage;
            }

            public function context(array $auth, int $campaignId): array
            {
                if ($campaignId !== 7) {
                    throw new CampaignException(
                        'campaign_not_found',
                        'Campaign was not found.',
                        404
                    );
                }
                return [
                    'auth' => $auth,
                    'campaign' => ['id' => 7, 'rpg_system_id' => 1],
                    'capabilities' => [
                        'canManage' => $this->canManage,
                    ],
                ];
            }
        };
        $this->guard = $guard;
        $this->service = new ProfessionCatalogService(
            $this->professionsDb,
            $guard
        );
    }

    protected function tearDown(): void
    {
        $this->professionsDb->close();
        parent::tearDown();
    }

    public function testCampaignCatalogReturnsEverySystemRecordAndCountsTypes(): void
    {
        $catalog = $this->service->campaignCatalog(7, ['user_id' => 2]);

        $this->assertSame(3, $catalog['meta']['total']);
        $this->assertSame(2, $catalog['meta']['basic']);
        $this->assertSame(1, $catalog['meta']['advanced']);
        $this->assertSame(7, $catalog['meta']['campaign_id']);
        $this->assertFalse($catalog['meta']['rules_available']);
        $this->assertFalse($catalog['items'][1]['is_main']);
        $this->assertTrue($catalog['items'][2]['is_advanced']);
    }

    public function testLegacyMechanicsRemainExplicitlyUnverified(): void
    {
        $catalog = $this->service->systemCatalog(1);
        $profession = $catalog['items'][0];

        $this->assertSame('unverified', $profession['development']['status']);
        $this->assertSame(
            'weapon_skill',
            $profession['development']['attributes'][0]['key']
        );
        $this->assertSame(
            'dowodzenie',
            $profession['development']['skills'][0]['raw']
        );
        $this->assertSame(
            'dowodzenie',
            $profession['development']['skills'][0]['display']
        );
        $this->assertSame(
            '/api/profession-assets/9/file',
            $profession['images']['female']['url']
        );
        $this->assertSame(
            2,
            $profession['development']['paths']['exits'][0]['professionId']
        );
        $this->assertTrue(
            $profession['development']['paths']['exits'][0]['linked']
        );
        $this->assertSame(
            'miecz',
            $profession['development']['equipment'][0]['notes']
        );
    }

    public function testUnknownSystemHasASeparateNotFoundError(): void
    {
        try {
            $this->service->systemCatalog(99);
            $this->fail('An unknown RPG system must be rejected.');
        } catch (CampaignException $exception) {
            $this->assertSame('rpg_system_not_found', $exception->errorCode());
            $this->assertSame(404, $exception->status());
        }
    }

    public function testGmChangesProfessionWithoutSpendingXpAndKeepsHistory(): void
    {
        $this->guard->setCanManage(true);

        $result = $this->service->changeCharacterProfession(
            7,
            10,
            ['user_id' => 2],
            ['professionId' => 2]
        );
        $rows = $this->professionsDb->table('character_professions')
            ->where('character_id', 10)->orderBy('id', 'ASC')
            ->get()->getResultArray();

        $this->assertTrue($result['changed']);
        $this->assertSame(0, $result['xpSpent']);
        $this->assertSame(1, $result['previousProfessionId']);
        $this->assertSame(2, $result['currentProfessionId']);
        $this->assertCount(2, $rows);
        $this->assertSame(0, (int) $rows[0]['is_current']);
        $this->assertSame(1, (int) $rows[0]['is_finished']);
        $this->assertNotNull($rows[0]['finished_at']);
        $this->assertSame(1, (int) $rows[0]['sort_order']);
        $this->assertSame(1, (int) $rows[1]['is_current']);
        $this->assertSame(2, (int) $rows[1]['profession_id']);
    }

    public function testProfessionChangeWorksBeforeOrderMigrationIsApplied(): void
    {
        $this->guard->setCanManage(true);
        $this->professionsDb->query(
            'ALTER TABLE character_professions DROP COLUMN sort_order'
        );

        $result = $this->service->changeCharacterProfession(
            7,
            10,
            ['user_id' => 2],
            ['professionId' => 2]
        );
        $rows = $this->professionsDb->table('character_professions')
            ->where('character_id', 10)->orderBy('id', 'ASC')
            ->get()->getResultArray();

        $this->assertTrue($result['changed']);
        $this->assertCount(2, $rows);
        $this->assertArrayNotHasKey('sort_order', $rows[0]);
        $this->assertSame(0, (int) $rows[0]['is_current']);
        $this->assertSame(1, (int) $rows[1]['is_current']);
    }

    public function testGmReordersAndDeletesPreviousProfessions(): void
    {
        $this->guard->setCanManage(true);
        $this->service->changeCharacterProfession(
            7,
            10,
            ['user_id' => 2],
            ['professionId' => 2]
        );
        $this->service->changeCharacterProfession(
            7,
            10,
            ['user_id' => 2],
            ['professionId' => 3]
        );
        $history = $this->professionsDb->table('character_professions')
            ->select('id')->where('character_id', 10)
            ->where('is_current', 0)->orderBy('sort_order', 'ASC')
            ->get()->getResultArray();
        $ids = array_map('intval', array_column($history, 'id'));
        $reversed = array_reverse($ids);

        $reordered = $this->service->reorderCharacterProfessions(
            7,
            10,
            ['user_id' => 2],
            ['historyIds' => $reversed]
        );
        $this->assertSame($reversed, $reordered['historyIds']);
        $first = $this->professionsDb->table('character_professions')
            ->where('id', $reversed[0])->get()->getRowArray();
        $this->assertSame(1, (int) $first['sort_order']);

        $deleted = $this->service->deleteCharacterProfession(
            7,
            10,
            $reversed[1],
            ['user_id' => 2]
        );
        $this->assertTrue($deleted['deleted']);
        $this->assertSame(0, $this->professionsDb
            ->table('character_professions')
            ->where('id', $reversed[1])->countAllResults());
    }

    public function testGmActivatesHistoricalProfessionBySwappingCurrentEntry(): void
    {
        $this->guard->setCanManage(true);
        $this->service->changeCharacterProfession(
            7,
            10,
            ['user_id' => 2],
            ['professionId' => 2]
        );
        $history = $this->professionsDb->table('character_professions')
            ->where('character_id', 10)
            ->where('profession_id', 1)->get()->getRowArray();

        $result = $this->service->activateCharacterProfession(
            7,
            10,
            (int) $history['id'],
            ['user_id' => 2]
        );
        $rows = $this->professionsDb->table('character_professions')
            ->where('character_id', 10)->orderBy('id', 'ASC')
            ->get()->getResultArray();

        $this->assertTrue($result['changed']);
        $this->assertSame(0, $result['xpSpent']);
        $this->assertCount(2, $rows);
        $this->assertSame(1, (int) $rows[0]['is_current']);
        $this->assertSame(0, (int) $rows[0]['is_finished']);
        $this->assertNull($rows[0]['finished_at']);
        $this->assertSame(0, (int) $rows[1]['is_current']);
        $this->assertSame(1, (int) $rows[1]['is_finished']);
        $this->assertSame(1, (int) $rows[1]['sort_order']);
    }

    public function testOnlyCampaignManagerCanChangeProfession(): void
    {
        try {
            $this->service->changeCharacterProfession(
                7,
                10,
                ['user_id' => 2],
                ['professionId' => 2]
            );
            $this->fail('A player must not change a profession directly.');
        } catch (CampaignException $exception) {
            $this->assertSame('forbidden', $exception->errorCode());
            $this->assertSame(403, $exception->status());
        }
    }

    private function createSchema(): void
    {
        foreach ([
            'CREATE TABLE rpg_systems (id INTEGER PRIMARY KEY, code TEXT, name TEXT)',
            'CREATE TABLE professions (id INTEGER PRIMARY KEY, system_id INTEGER, name TEXT, description TEXT, details TEXT, is_advanced INTEGER, is_main INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE characters (id INTEGER PRIMARY KEY, campaign_id INTEGER, system_id INTEGER, name TEXT)',
            'CREATE TABLE character_professions (id INTEGER PRIMARY KEY AUTOINCREMENT, character_id INTEGER, profession_id INTEGER, is_current INTEGER, is_finished INTEGER, started_at TEXT, finished_at TEXT, sort_order INTEGER DEFAULT 0)',
            'CREATE TABLE profession_attributes (id INTEGER PRIMARY KEY, profession_id INTEGER, attribute_key TEXT, attribute_group TEXT, value INTEGER)',
            'CREATE TABLE profession_definitions (id INTEGER PRIMARY KEY, profession_id INTEGER, definition_id INTEGER, metadata TEXT)',
            'CREATE TABLE profession_paths (id INTEGER PRIMARY KEY, profession_id INTEGER, related_profession_id INTEGER, relation_type TEXT)',
            'CREATE TABLE profession_equipment (id INTEGER PRIMARY KEY, profession_id INTEGER, definition_id INTEGER, item_name TEXT, quantity INTEGER, notes TEXT)',
            'CREATE TABLE profession_assets (id INTEGER PRIMARY KEY, profession_id INTEGER, slot TEXT, storage_key TEXT, original_name TEXT, mime_type TEXT, byte_size INTEGER, width INTEGER, height INTEGER, created_by_user_id INTEGER, created_at TEXT)',
        ] as $sql) {
            $this->professionsDb->query($sql);
        }
    }

    private function seed(): void
    {
        $this->professionsDb->table('rpg_systems')->insert([
            'id' => 1,
            'code' => 'wfrp2ed',
            'name' => 'Warhammer Fantasy Roleplay 2e',
        ]);
        $this->professionsDb->table('professions')->insertBatch([
            ['id' => 1, 'system_id' => 1, 'name' => 'żołnierz', 'description' => 'Opis', 'details' => null, 'is_advanced' => 0, 'is_main' => 1],
            ['id' => 2, 'system_id' => 1, 'name' => 'szuler', 'description' => 'Opis', 'details' => null, 'is_advanced' => 0, 'is_main' => 0],
            ['id' => 3, 'system_id' => 1, 'name' => 'kapitan', 'description' => 'Opis', 'details' => null, 'is_advanced' => 1, 'is_main' => 1],
        ]);
        $this->professionsDb->table('characters')->insert([
            'id' => 10,
            'campaign_id' => 7,
            'system_id' => 1,
            'name' => 'Bohater',
        ]);
        $this->professionsDb->table('character_professions')->insert([
            'character_id' => 10,
            'profession_id' => 1,
            'is_current' => 1,
            'is_finished' => 0,
            'started_at' => '2026-01-01 12:00:00',
            'finished_at' => null,
            'sort_order' => 1,
        ]);
        $this->professionsDb->table('profession_attributes')->insert([
            'id' => 1, 'profession_id' => 1, 'attribute_key' => 'weapon_skill',
            'attribute_group' => 'primary', 'value' => 10,
        ]);
        $this->professionsDb->table('profession_definitions')->insert([
            'id' => 1, 'profession_id' => 1, 'definition_id' => null,
            'metadata' => json_encode(['list_type' => 'skills', 'raw' => 'dowodzenie']),
        ]);
        $this->professionsDb->table('profession_paths')->insert([
            'id' => 1, 'profession_id' => 1, 'related_profession_id' => 2,
            'relation_type' => 'exit',
        ]);
        $this->professionsDb->table('profession_equipment')->insert([
            'id' => 1, 'profession_id' => 1, 'definition_id' => null,
            'item_name' => null, 'quantity' => 1, 'notes' => 'miecz',
        ]);
        $this->professionsDb->table('profession_assets')->insert([
            'id' => 9,
            'profession_id' => 1,
            'slot' => 'female',
            'storage_key' => str_repeat('b', 32) . '.png',
            'original_name' => 'soldier.png',
            'mime_type' => 'image/png',
            'byte_size' => 100,
            'width' => 500,
            'height' => 1200,
            'created_by_user_id' => 1,
            'created_at' => '2026-01-01 12:00:00',
        ]);
    }
}
