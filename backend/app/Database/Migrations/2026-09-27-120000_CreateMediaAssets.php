<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMediaAssets extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('media_assets')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'owner_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 32],
            'provider_asset_id' => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
            'public_id' => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
            'resource_type' => ['type' => 'VARCHAR', 'constraint' => 32],
            'category' => ['type' => 'VARCHAR', 'constraint' => 64],
            'original_filename' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 150],
            'file_size' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'width' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'height' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'duration' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'unsigned' => true, 'null' => true],
            'visibility' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'private'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pending'],
            'metadata' => ['type' => 'JSON', 'null' => true],
            'upload_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['provider', 'public_id'], 'uq_media_assets_provider_public_id');
        $this->forge->addUniqueKey(
            ['provider', 'provider_asset_id'],
            'uq_media_assets_provider_asset'
        );
        $this->forge->addKey(['campaign_id', 'visibility', 'status'], false, false, 'idx_media_assets_campaign_access');
        $this->forge->addKey(['owner_user_id', 'visibility', 'status'], false, false, 'idx_media_assets_owner_access');
        $this->forge->addKey(['category', 'status'], false, false, 'idx_media_assets_category_status');
        $this->forge->addForeignKey('owner_user_id', 'users', 'id', 'CASCADE', 'SET NULL', 'fk_media_assets_owner');
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE', 'fk_media_assets_campaign');
        $this->forge->createTable('media_assets');
    }

    public function down()
    {
        $this->forge->dropTable('media_assets', true);
    }
}
