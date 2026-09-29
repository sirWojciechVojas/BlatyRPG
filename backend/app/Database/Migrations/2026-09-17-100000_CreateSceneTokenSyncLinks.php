<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSceneTokenSyncLinks extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('scene_token_sync_links')) return;

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'source_scene_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'source_token_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'target_scene_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'target_token_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'diverged_fields_json' => ['type' => 'JSON', 'null' => true],
            'last_synced_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'target_token_id']);
        $this->forge->addUniqueKey(['campaign_id', 'source_token_id', 'target_token_id']);
        $this->forge->addKey(['campaign_id', 'source_token_id', 'enabled']);
        $this->forge->addKey(['campaign_id', 'target_scene_id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('source_scene_id', 'scenes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('target_scene_id', 'scenes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('source_token_id', 'scene_tokens', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('target_token_id', 'scene_tokens', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('scene_token_sync_links');
    }

    public function down()
    {
        $this->forge->dropTable('scene_token_sync_links', true);
    }
}
