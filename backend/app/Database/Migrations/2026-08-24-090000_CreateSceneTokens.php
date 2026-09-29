<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSceneTokens extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('scene_tokens')) {
            return;
        }
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'scene_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'character_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'image_url' => ['type' => 'VARCHAR', 'constraint' => 2048, 'null' => true],
            'x' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'y' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
            'width' => ['type' => 'DECIMAL', 'constraint' => '10,3', 'default' => 100],
            'height' => ['type' => 'DECIMAL', 'constraint' => '10,3', 'default' => 100],
            'rotation' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 0],
            'elevation' => ['type' => 'DECIMAL', 'constraint' => '10,3', 'default' => 0],
            'disposition' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'neutral'],
            'hidden' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'locked' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'bars_json' => ['type' => 'JSON', 'null' => true],
            'statuses_json' => ['type' => 'JSON', 'null' => true],
            'vision_json' => ['type' => 'JSON', 'null' => true],
            'light_json' => ['type' => 'JSON', 'null' => true],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['scene_id', 'sort_order', 'id']);
        $this->forge->addKey(['campaign_id', 'scene_id', 'updated_at']);
        $this->forge->addKey(['character_id', 'scene_id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('scene_id', 'scenes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('scene_tokens');
    }

    public function down()
    {
        $this->forge->dropTable('scene_tokens', true);
    }
}
