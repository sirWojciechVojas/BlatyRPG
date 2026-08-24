<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSceneLightsAndDarkness extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('darkness_level', 'scenes')) {
            $this->forge->addColumn('scenes', [
                'darkness_level' => [
                    'type' => 'DECIMAL',
                    'constraint' => '4,3',
                    'default' => 0.2,
                    'after' => 'background_color',
                ],
            ]);
        }
        if ($this->db->tableExists('scene_lights')) return;
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'scene_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'x' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'y' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'bright_radius' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 200],
            'dim_radius' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 400],
            'color' => ['type' => 'VARCHAR', 'constraint' => 9, 'default' => '#FFD27A'],
            'intensity' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 1],
            'enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'hidden' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
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
        $this->forge->createTable('scene_lights');
    }

    public function down()
    {
        $this->forge->dropTable('scene_lights', true);
        if ($this->db->fieldExists('darkness_level', 'scenes')) {
            $this->forge->dropColumn('scenes', 'darkness_level');
        }
    }
}
