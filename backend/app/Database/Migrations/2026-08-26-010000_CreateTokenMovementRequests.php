<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTokenMovementRequests extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('token_movement_requests')) return;
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'scene_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'token_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'requested_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'resolved_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'origin_x' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'origin_y' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'target_x' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'target_y' => ['type' => 'DECIMAL', 'constraint' => '12,3'],
            'waypoints_json' => ['type' => 'JSON', 'null' => true],
            'cost' => ['type' => 'DECIMAL', 'constraint' => '10,3'],
            'spent_at_request' => ['type' => 'DECIMAL', 'constraint' => '10,3'],
            'range_at_request' => ['type' => 'DECIMAL', 'constraint' => '10,3'],
            'token_revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pending'],
            'resolved_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'status', 'created_at']);
        $this->forge->addKey(['token_id', 'status']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('scene_id', 'scenes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('token_id', 'scene_tokens', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('requested_by_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('resolved_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('token_movement_requests');
    }

    public function down()
    {
        $this->forge->dropTable('token_movement_requests', true);
    }
}
