<?php

use App\Database\Migrations\AddCharacterProfessionOrder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

require_once APPPATH
    . 'Database/Migrations/2026-09-23-120000_AddCharacterProfessionOrder.php';

/** @internal */
final class CharacterProfessionOrderMigrationTest extends CIUnitTestCase
{
    private BaseConnection $migrationDb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrationDb = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $this->migrationDb->query(
            'CREATE TABLE character_professions ('
                . 'id INTEGER PRIMARY KEY AUTOINCREMENT,'
                . 'character_id INTEGER,profession_id INTEGER,'
                . 'is_current INTEGER,is_finished INTEGER,'
                . 'started_at TEXT,finished_at TEXT)'
        );
        $this->migrationDb->table('character_professions')->insertBatch([
            [
                'character_id' => 4,
                'profession_id' => 10,
                'is_current' => 0,
                'is_finished' => 1,
            ],
            [
                'character_id' => 4,
                'profession_id' => 20,
                'is_current' => 1,
                'is_finished' => 0,
            ],
            [
                'character_id' => 8,
                'profession_id' => 30,
                'is_current' => 1,
                'is_finished' => 0,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->migrationDb->close();
        parent::tearDown();
    }

    public function testAddsAndBackfillsStableOrderPerCharacter(): void
    {
        $migration = new AddCharacterProfessionOrder(
            Database::forge($this->migrationDb)
        );
        $migration->up();
        $migration->up();

        $this->assertTrue($this->migrationDb->fieldExists(
            'sort_order',
            'character_professions'
        ));
        $rows = $this->migrationDb->table('character_professions')
            ->select('character_id,sort_order')->orderBy('id', 'ASC')
            ->get()->getResultArray();
        $this->assertSame([1, 2, 1], array_map(
            'intval',
            array_column($rows, 'sort_order')
        ));
    }
}
