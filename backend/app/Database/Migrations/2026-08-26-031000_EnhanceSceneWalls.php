<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Adds wall-manager presentation and activation fields. */
class EnhanceSceneWalls extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('scene_walls')) return;

        $fields = [];
        if (!$this->db->fieldExists('name', 'scene_walls')) {
            $fields['name'] = [
                'type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'scene_id',
            ];
        }
        if (!$this->db->fieldExists('color', 'scene_walls')) {
            $fields['color'] = [
                'type' => 'VARCHAR', 'constraint' => 9, 'null' => true, 'after' => 'door_state',
            ];
        }
        if (!$this->db->fieldExists('enabled', 'scene_walls')) {
            $fields['enabled'] = [
                'type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'color',
            ];
        }
        if (!$this->db->fieldExists('hidden', 'scene_walls')) {
            $fields['hidden'] = [
                'type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'enabled',
            ];
        }
        if ($fields !== []) $this->forge->addColumn('scene_walls', $fields);
    }

    public function down()
    {
        if (!$this->db->tableExists('scene_walls')) return;
        foreach (['hidden', 'enabled', 'color', 'name'] as $field) {
            if ($this->db->fieldExists($field, 'scene_walls')) {
                $this->forge->dropColumn('scene_walls', $field);
            }
        }
    }
}
