<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Persistent GM playlists and independent queues for every campaign channel. */
class CreateJukeboxPlaylistsAndQueues extends Migration
{
    public function up()
    {
        $this->createPlaylists();
        $this->createPlaylistItems();
        $this->createQueueItems();
    }

    public function down()
    {
        $this->forge->dropTable('campaign_jukebox_queue_items', true);
        $this->forge->dropTable('audio_playlist_items', true);
        $this->forge->dropTable('audio_playlists', true);
    }

    private function createPlaylists(): void
    {
        if ($this->db->tableExists('audio_playlists')) {
            return;
        }
        $this->forge->addField([
            'id' => $this->bigId(),
            'owner_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['owner_user_id', 'name']);
        $this->forge->addForeignKey('owner_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('audio_playlists');
    }

    private function createPlaylistItems(): void
    {
        if ($this->db->tableExists('audio_playlist_items')) {
            return;
        }
        $this->forge->addField([
            'id' => $this->bigId(),
            'playlist_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'audio_track_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['playlist_id', 'sort_order', 'id']);
        $this->forge->addForeignKey('playlist_id', 'audio_playlists', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('audio_track_id', 'audio_tracks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('audio_playlist_items');
    }

    private function createQueueItems(): void
    {
        if ($this->db->tableExists('campaign_jukebox_queue_items')) {
            return;
        }
        $this->forge->addField([
            'id' => $this->bigId(),
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'channel_id' => ['type' => 'VARCHAR', 'constraint' => 32],
            'audio_track_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'playlist_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'added_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'channel_id', 'sort_order', 'id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('audio_track_id', 'audio_tracks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('playlist_id', 'audio_playlists', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('added_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('campaign_jukebox_queue_items');
    }

    private function bigId(): array
    {
        return [
            'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
            'auto_increment' => true,
        ];
    }
}
