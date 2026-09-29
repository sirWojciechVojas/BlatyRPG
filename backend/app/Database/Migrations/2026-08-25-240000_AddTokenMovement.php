<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTokenMovement extends Migration
{
    private const FIELDS = ['movement_range', 'movement_spent', 'movement_reset_mode'];

    public function up()
    {
        if (!$this->db->tableExists('scene_tokens')) return;
        if (!$this->db->fieldExists('movement_range', 'scene_tokens')) {
            $this->forge->addColumn('scene_tokens', [
                'movement_range' => [
                    'type' => 'DECIMAL', 'constraint' => '10,3',
                    'default' => 6, 'after' => 'facing_handle_enabled',
                ],
            ]);
        }
        if (!$this->db->fieldExists('movement_spent', 'scene_tokens')) {
            $this->forge->addColumn('scene_tokens', [
                'movement_spent' => [
                    'type' => 'DECIMAL', 'constraint' => '10,3',
                    'default' => 0, 'after' => 'movement_range',
                ],
            ]);
        }
        if (!$this->db->fieldExists('movement_reset_mode', 'scene_tokens')) {
            $this->forge->addColumn('scene_tokens', [
                'movement_reset_mode' => [
                    'type' => 'VARCHAR', 'constraint' => 16,
                    'default' => 'turn', 'after' => 'movement_spent',
                ],
            ]);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('scene_tokens')) return;
        foreach (array_reverse(self::FIELDS) as $field) {
            if ($this->db->fieldExists($field, 'scene_tokens')) {
                $this->forge->dropColumn('scene_tokens', $field);
            }
        }
    }
}
