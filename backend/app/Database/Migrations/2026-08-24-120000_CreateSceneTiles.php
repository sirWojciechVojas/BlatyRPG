<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSceneTiles extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('scene_tiles')) return;
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'scene_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150, 'default' => 'Tile'],
            'asset_url' => ['type' => 'TEXT'],
            'media_type' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'image'],
            'layer' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'background'],
            'x' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'y' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'width' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 200],
            'height' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 200],
            'rotation' => ['type' => 'DECIMAL', 'constraint' => '10,3', 'default' => 0],
            'opacity' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 1],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'hidden' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'locked' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'autoplay' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'loop' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'muted' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['scene_id', 'layer', 'sort_order', 'id']);
        $this->forge->addKey(['campaign_id', 'scene_id', 'updated_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('scene_id', 'scenes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('scene_tiles');
    }

    public function down()
    {
        $this->forge->dropTable('scene_tiles', true);
    }
}
