<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateScenes extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('scenes')) {
            $this->createScenes();
        }
        if (!$this->db->tableExists('campaign_scene_state')) {
            $this->createCampaignState();
        }
    }

    public function down()
    {
        $this->forge->dropTable('campaign_scene_state', true);
        $this->forge->dropTable('scenes', true);
    }

    private function createScenes(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
            'background_url' => ['type' => 'VARCHAR', 'constraint' => 2048, 'null' => true],
            'width' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 4000],
            'height' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 3000],
            'padding' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'background_color' => ['type' => 'VARCHAR', 'constraint' => 9, 'default' => '#000000'],
            'grid_type' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'square'],
            'grid_size' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 100],
            'grid_distance' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 1],
            'grid_unit' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'm'],
            'grid_offset_x' => ['type' => 'DECIMAL', 'constraint' => '10,3', 'default' => 0],
            'grid_offset_y' => ['type' => 'DECIMAL', 'constraint' => '10,3', 'default' => 0],
            'grid_color' => ['type' => 'VARCHAR', 'constraint' => 9, 'default' => '#000000'],
            'grid_opacity' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 0.35],
            'is_visible' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'is_visible', 'sort_order', 'id']);
        $this->forge->addKey(['campaign_id', 'sort_order', 'id']);
        $this->forge->addKey(['campaign_id', 'updated_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('scenes');
    }

    private function createCampaignState(): void
    {
        $this->forge->addField([
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'active_scene_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'updated_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('campaign_id', true);
        $this->forge->addKey('active_scene_id');
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('active_scene_id', 'scenes', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('campaign_scene_state');
    }
}
