<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFogOfWar extends Migration
{
    public function up()
    {
        $this->forge->addColumn('scenes', [
            'fog_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'fog_exploration'],
            'dynamic_vision' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'fog_enabled'],
            'exploration_memory' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'dynamic_vision'],
            'fog_unexplored_color' => ['type' => 'VARCHAR', 'constraint' => 9, 'default' => '#05070B', 'after' => 'exploration_memory'],
            'fog_unexplored_opacity' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 1, 'after' => 'fog_unexplored_color'],
            'fog_explored_opacity' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 0.62, 'after' => 'fog_unexplored_opacity'],
            'fog_edge_softness' => ['type' => 'DECIMAL', 'constraint' => '7,2', 'default' => 12, 'after' => 'fog_explored_opacity'],
            'fog_update_during_drag' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'after' => 'fog_edge_softness'],
        ]);

        if (!$this->db->tableExists('scene_fog_states')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'scene_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'cell_size' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 64],
                'explored_ranges_json' => ['type' => 'LONGTEXT', 'null' => true],
                'forced_hidden_ranges_json' => ['type' => 'LONGTEXT', 'null' => true],
                'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['scene_id', 'user_id']);
            $this->forge->addKey(['campaign_id', 'scene_id']);
            $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('scene_id', 'scenes', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('scene_fog_states');
        }
    }

    public function down()
    {
        $this->forge->dropTable('scene_fog_states', true);
        foreach (array_reverse([
            'fog_enabled', 'dynamic_vision', 'exploration_memory',
            'fog_unexplored_color', 'fog_unexplored_opacity',
            'fog_explored_opacity', 'fog_edge_softness', 'fog_update_during_drag',
        ]) as $field) {
            if ($this->db->fieldExists($field, 'scenes')) $this->forge->dropColumn('scenes', $field);
        }
    }
}
