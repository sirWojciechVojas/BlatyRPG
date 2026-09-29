<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Persistent audio catalog and the last authoritative state of every campaign jukebox. */
class CreateVoiceAndJukebox extends Migration
{
    public function up()
    {
        $this->createAudioLibraries();
        $this->createAudioTracks();
        $this->createCampaignAudioTracks();
        $this->createCampaignJukeboxSettings();
    }

    public function down()
    {
        $this->forge->dropTable('campaign_jukebox_settings', true);
        $this->forge->dropTable('campaign_audio_tracks', true);
        $this->forge->dropTable('audio_tracks', true);
        $this->forge->dropTable('audio_libraries', true);
    }

    private function createAudioLibraries(): void
    {
        if ($this->db->tableExists('audio_libraries')) {
            return;
        }
        $this->forge->addField([
            'id' => $this->bigId(),
            'name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'scope' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'system'],
            'system_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'setting_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'owner_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['scope', 'system_id', 'setting_id', 'is_active']);
        $this->forge->addKey(['owner_user_id', 'is_active']);
        $this->forge->addForeignKey('system_id', 'rpg_systems', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('setting_id', 'rpg_universes', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('owner_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('audio_libraries');
    }

    private function createAudioTracks(): void
    {
        if ($this->db->tableExists('audio_tracks')) {
            return;
        }
        $this->forge->addField([
            'id' => $this->bigId(),
            'library_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'owner_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 180],
            'category' => ['type' => 'VARCHAR', 'constraint' => 16],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 24],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 48, 'null' => true],
            'provider_reference' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'storage_key' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'external_url' => ['type' => 'VARCHAR', 'constraint' => 2048, 'null' => true],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'extension' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
            'byte_size' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'sha256' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'duration_seconds' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'null' => true],
            'loop_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'tags_json' => ['type' => 'JSON', 'null' => true],
            'thumbnail_url' => ['type' => 'VARCHAR', 'constraint' => 2048, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'ready'],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['library_id', 'category', 'status', 'deleted_at']);
        $this->forge->addKey(['owner_user_id', 'deleted_at']);
        $this->forge->addUniqueKey('storage_key');
        $this->forge->addForeignKey('library_id', 'audio_libraries', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('owner_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('audio_tracks');
    }

    private function createCampaignAudioTracks(): void
    {
        if ($this->db->tableExists('campaign_audio_tracks')) {
            return;
        }
        $this->forge->addField([
            'id' => $this->bigId(),
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'audio_track_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'added_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'is_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'audio_track_id']);
        $this->forge->addKey(['campaign_id', 'is_enabled', 'sort_order']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('audio_track_id', 'audio_tracks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('added_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('campaign_audio_tracks');
    }

    private function createCampaignJukeboxSettings(): void
    {
        if ($this->db->tableExists('campaign_jukebox_settings')) {
            return;
        }
        $this->forge->addField([
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'state_json' => ['type' => 'JSON', 'null' => false],
            'settings_json' => ['type' => 'JSON', 'null' => false],
            'revision' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 1],
            'updated_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('campaign_id', true);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('updated_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('campaign_jukebox_settings');
    }

    private function bigId(): array
    {
        return [
            'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
            'auto_increment' => true,
        ];
    }
}
