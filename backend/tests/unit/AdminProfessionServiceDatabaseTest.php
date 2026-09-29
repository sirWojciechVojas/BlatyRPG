<?php

use App\Services\Admin\AdminException;
use App\Services\Admin\AdminProfessionPayloadValidator;
use App\Services\Admin\AdminProfessionService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/** @internal */
final class AdminProfessionServiceDatabaseTest extends CIUnitTestCase
{
    private BaseConnection $professionDb;
    private AdminProfessionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->professionDb = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $this->createSchema();
        $this->seed();
        $this->service = new AdminProfessionService(
            $this->professionDb,
            new AdminProfessionPayloadValidator()
        );
    }

    protected function tearDown(): void
    {
        $this->professionDb->close();
        parent::tearDown();
    }

    public function testOnlyAdministratorsCanReadTheEditorialCatalog(): void
    {
        try {
            $this->service->overview(['user_id' => 2]);
            $this->fail('A non-administrator must not read the editor catalog.');
        } catch (AdminException $exception) {
            $this->assertSame(403, $exception->status());
        }
    }

    public function testOverviewIncludesSystemsFlagsAndSafeDeletionState(): void
    {
        $result = $this->service->overview($this->admin());

        $this->assertCount(2, $result['items']);
        $this->assertSame('wfrp2ed', $result['systems'][0]['code']);
        $soldier = array_values(array_filter(
            $result['items'],
            static fn (array $item): bool => $item['id'] === 1
        ))[0];
        $this->assertFalse($soldier['isAdvanced']);
        $this->assertSame(1, $soldier['usage']['characters']);
        $this->assertFalse($soldier['canDelete']);
        $this->assertSame(
            'Znajomość języka (kislevski), Znajomość języka '
            . '(staroświatowy — Reikspiel)',
            $soldier['skillsDecoded']
        );
        $this->assertSame('/api/profession-assets/7/file', $soldier['images']['male']['url']);
        $this->assertSame('46(6)', $soldier['skillItems'][0]['raw']);
        $skillOptions = array_column(
            $result['requirementOptions']['skills'],
            null,
            'raw'
        );
        $this->assertSame(
            'Znajomość języka (kislevski)',
            $skillOptions['46(6)']['display']
        );
    }

    public function testAdministratorCanCreateAndUpdateAProfession(): void
    {
        $created = $this->service->create($this->admin(), [
            'systemId' => 1,
            'name' => 'Łowca czarownic',
            'description' => 'Opis katalogowy',
            'details' => null,
            'isAdvanced' => true,
            'isMain' => false,
            'skills' => '3,46(9)',
            'talents' => '3|13',
        ])['profession'];

        $this->assertSame('Łowca czarownic', $created['name']);
        $this->assertTrue($created['isAdvanced']);
        $this->assertFalse($created['isMain']);
        $this->assertSame(
            'Czytanie i pisanie, Znajomość języka (staroświatowy — Reikspiel)',
            $created['skillsDecoded']
        );

        $updated = $this->service->update(
            $this->admin(),
            (int) $created['id'],
            [
                'name' => 'Łowca magii',
                'description' => 'Nowy opis',
                'details' => 'Uwagi',
                'isAdvanced' => false,
                'isMain' => true,
                'skills' => '12',
                'talents' => '',
                'updatedAt' => $created['updatedAt'],
            ]
        )['profession'];

        $this->assertSame('Łowca magii', $updated['name']);
        $this->assertSame('Uwagi', $updated['details']);
        $this->assertSame('Leczenie', $updated['skillsDecoded']);
        $this->assertNotSame($created['updatedAt'], $updated['updatedAt']);

        try {
            $this->service->update($this->admin(), (int) $created['id'], [
                'name' => 'Nieaktualny zapis',
                'updatedAt' => $created['updatedAt'],
            ]);
            $this->fail('A stale profession update must be rejected.');
        } catch (AdminException $exception) {
            $this->assertSame(409, $exception->status());
            $this->assertSame('profession_changed', $exception->errorCode());
        }
    }

    public function testDeletionProtectsReferencesAndRemovesOwnedMechanics(): void
    {
        try {
            $this->service->delete($this->admin(), 1, [
                'updatedAt' => '2026-01-01 12:00:00',
            ]);
            $this->fail('A profession used by a character must be protected.');
        } catch (AdminException $exception) {
            $this->assertSame('profession_in_use', $exception->errorCode());
            $this->assertSame(1, $exception->details()['characterUsage']);
        }

        $created = $this->service->create($this->admin(), [
            'systemId' => 1,
            'name' => 'Nowa profesja',
            'isAdvanced' => false,
            'isMain' => true,
        ])['profession'];
        $this->professionDb->table('profession_attributes')->insert([
            'profession_id' => $created['id'],
            'attribute_key' => 'weapon_skill',
            'value' => 5,
        ]);

        $result = $this->service->delete(
            $this->admin(),
            (int) $created['id'],
            ['updatedAt' => $created['updatedAt']]
        );

        $this->assertTrue($result['deleted']);
        $this->assertSame(0, $this->professionDb->table('professions')
            ->where('id', $created['id'])->countAllResults());
        $this->assertSame(0, $this->professionDb->table('profession_attributes')
            ->where('profession_id', $created['id'])->countAllResults());
    }

    private function admin(): array
    {
        return ['user_id' => 1, 'role' => 'admin'];
    }

    private function createSchema(): void
    {
        foreach ([
            'CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT, deleted_at TEXT)',
            'CREATE TABLE rpg_systems (id INTEGER PRIMARY KEY, code TEXT, name TEXT)',
            'CREATE TABLE professions (id INTEGER PRIMARY KEY AUTOINCREMENT, system_id INTEGER, name TEXT, description TEXT, details TEXT, is_advanced INTEGER, is_main INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE character_professions (id INTEGER PRIMARY KEY AUTOINCREMENT, character_id INTEGER, profession_id INTEGER)',
            'CREATE TABLE profession_attributes (id INTEGER PRIMARY KEY AUTOINCREMENT, profession_id INTEGER, attribute_key TEXT, value INTEGER)',
            'CREATE TABLE profession_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, profession_id INTEGER, definition_id INTEGER, metadata TEXT)',
            'CREATE TABLE profession_paths (id INTEGER PRIMARY KEY AUTOINCREMENT, profession_id INTEGER, related_profession_id INTEGER, relation_type TEXT)',
            'CREATE TABLE profession_equipment (id INTEGER PRIMARY KEY AUTOINCREMENT, profession_id INTEGER, definition_id INTEGER, item_name TEXT, quantity INTEGER, notes TEXT)',
            'CREATE TABLE profession_assets (id INTEGER PRIMARY KEY AUTOINCREMENT, profession_id INTEGER, slot TEXT, storage_key TEXT, original_name TEXT, mime_type TEXT, byte_size INTEGER, width INTEGER, height INTEGER, created_by_user_id INTEGER, created_at TEXT)',
        ] as $statement) {
            $this->professionDb->query($statement);
        }
    }

    private function seed(): void
    {
        $this->professionDb->table('users')->insertBatch([
            ['id' => 1, 'role' => 'admin', 'deleted_at' => null],
            ['id' => 2, 'role' => 'user', 'deleted_at' => null],
        ]);
        $this->professionDb->table('rpg_systems')->insert([
            'id' => 1,
            'code' => 'wfrp2ed',
            'name' => 'Warhammer Fantasy Roleplay 2e',
        ]);
        $this->professionDb->table('professions')->insertBatch([
            [
                'id' => 1,
                'system_id' => 1,
                'name' => 'Żołnierz',
                'description' => 'Opis',
                'details' => null,
                'is_advanced' => 0,
                'is_main' => 1,
                'created_at' => '2026-01-01 12:00:00',
                'updated_at' => '2026-01-01 12:00:00',
            ],
            [
                'id' => 2,
                'system_id' => 1,
                'name' => 'Kapitan',
                'description' => 'Opis',
                'details' => null,
                'is_advanced' => 1,
                'is_main' => 1,
                'created_at' => '2026-01-01 12:00:00',
                'updated_at' => '2026-01-01 12:00:00',
            ],
        ]);
        $this->professionDb->table('character_professions')->insert([
            'character_id' => 10,
            'profession_id' => 1,
        ]);
        $this->professionDb->table('profession_paths')->insert([
            'profession_id' => 1,
            'related_profession_id' => 2,
            'relation_type' => 'exit',
        ]);
        $this->professionDb->table('profession_definitions')->insert([
            'profession_id' => 1,
            'definition_id' => null,
            'metadata' => json_encode([
                'list_type' => 'skills',
                'raw' => '46(6),46(9)',
            ]),
        ]);
        $this->professionDb->table('profession_assets')->insert([
            'id' => 7,
            'profession_id' => 1,
            'slot' => 'male',
            'storage_key' => str_repeat('a', 32) . '.webp',
            'original_name' => 'soldier.webp',
            'mime_type' => 'image/webp',
            'byte_size' => 1200,
            'width' => 800,
            'height' => 1600,
            'created_by_user_id' => 1,
            'created_at' => '2026-01-01 12:00:00',
        ]);
    }
}
