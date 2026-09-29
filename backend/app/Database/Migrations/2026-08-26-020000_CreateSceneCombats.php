<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSceneCombats extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('scene_combats')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'scene_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'round' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
                'turn_index' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
                'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
                'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('scene_id');
            $this->forge->addKey(['campaign_id', 'active']);
            $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('scene_id', 'scenes', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
            $this->forge->createTable('scene_combats');
        }
        if ($this->db->tableExists('scene_combatants')) return;
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'combat_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'token_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'initiative' => ['type' => 'DECIMAL', 'constraint' => '10,3', 'default' => 0],
            'sort_order' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'hidden' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'defeated' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['combat_id', 'token_id']);
        $this->forge->addKey(['combat_id', 'initiative', 'sort_order']);
        $this->forge->addForeignKey('combat_id', 'scene_combats', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('token_id', 'scene_tokens', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('scene_combatants');
    }

    public function down()
    {
        $this->forge->dropTable('scene_combatants', true);
        $this->forge->dropTable('scene_combats', true);
    }
}
