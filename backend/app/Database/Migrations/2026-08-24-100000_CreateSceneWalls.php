<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSceneWalls extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('scene_walls')) return;
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'scene_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'type' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'wall'],
            'x1' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'y1' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'x2' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'y2' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'blocks_movement' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'blocks_sight' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'blocks_light' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'door_state' => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['scene_id', 'id']);
        $this->forge->addKey(['campaign_id', 'scene_id', 'updated_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('scene_id', 'scenes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('scene_walls');
    }

    public function down()
    {
        $this->forge->dropTable('scene_walls', true);
    }
}
