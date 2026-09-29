<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class MakeLegacyMediaKeysNullable extends Migration
{
    private const TABLES = [
        'map_assets' => 100,
        'handout_assets' => 255,
        'compendium_assets' => 255,
        'token_template_assets' => 80,
        'profession_assets' => 80,
    ];

    public function up()
    {
        foreach (self::TABLES as $table => $length) {
            if ($this->db->tableExists($table) && $this->db->fieldExists('storage_key', $table)) {
                $this->forge->modifyColumn($table, [
                    'storage_key' => [
                        'name' => 'storage_key', 'type' => 'VARCHAR',
                        'constraint' => $length, 'null' => true,
                    ],
                ]);
            }
        }
    }

    public function down()
    {
        foreach (self::TABLES as $table => $length) {
            if (!$this->db->tableExists($table) || !$this->db->fieldExists('storage_key', $table)
                || $this->db->table($table)->where('storage_key', null)->countAllResults() > 0) {
                continue;
            }
            $this->forge->modifyColumn($table, [
                'storage_key' => [
                    'name' => 'storage_key', 'type' => 'VARCHAR',
                    'constraint' => $length, 'null' => false,
                ],
            ]);
        }
    }
}
