<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTokenRotationHandles extends Migration
{
    private const FIELDS = ['rotation_handle_enabled', 'facing_handle_enabled'];

    public function up()
    {
        if (!$this->db->tableExists('scene_tokens')) return;
        foreach (self::FIELDS as $field) {
            if ($this->db->fieldExists($field, 'scene_tokens')) continue;
            $this->forge->addColumn('scene_tokens', [
                $field => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0,
                    'after' => 'facing',
                ],
            ]);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('scene_tokens')) return;
        foreach (self::FIELDS as $field) {
            if ($this->db->fieldExists($field, 'scene_tokens')) {
                $this->forge->dropColumn('scene_tokens', $field);
            }
        }
    }
}
