<?php

use App\Database\Migrations\CreateTokenTemplateLibrary;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

require_once APPPATH . 'Database/Migrations/2026-09-18-100000_CreateTokenTemplateLibrary.php';

/** @internal */
final class TokenTemplateMigrationTest extends CIUnitTestCase
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
        $this->migrationDb->query('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $this->migrationDb->query(
            'CREATE TABLE scene_tokens (id INTEGER PRIMARY KEY, character_id INTEGER)'
        );
        $this->migrationDb->table('scene_tokens')->insert([
            'id' => 9,
            'character_id' => null,
        ]);
    }

    protected function tearDown(): void
    {
        $this->migrationDb->close();
        parent::tearDown();
    }

    public function testCreatesLibraryAndKeepsExistingTokensCompatible(): void
    {
        $migration = new CreateTokenTemplateLibrary(Database::forge($this->migrationDb));
        $migration->up();

        $this->assertTrue($this->migrationDb->tableExists('token_template_assets'));
        $this->assertTrue($this->migrationDb->tableExists('token_templates'));
        $this->assertTrue($this->migrationDb->fieldExists('revision', 'token_templates'));
        $this->assertTrue($this->migrationDb->fieldExists('deleted_at', 'token_templates'));
        $fields = array_column($this->migrationDb->getFieldData('scene_tokens'), 'name');
        $this->assertContains('token_template_id', $fields);
        $this->assertContains('token_template_asset_id', $fields);

        $legacy = $this->migrationDb->table('scene_tokens')->where('id', 9)
            ->get()->getRowArray();
        $this->assertNull($legacy['token_template_id']);
        $this->assertNull($legacy['token_template_asset_id']);
    }
}
