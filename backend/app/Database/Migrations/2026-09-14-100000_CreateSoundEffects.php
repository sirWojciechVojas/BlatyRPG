<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Campaign soundpad layout and the durable state required for reconnect sync. */
class CreateSoundEffects extends Migration
{
    public function up()
    {
        $this->createSettings();
        $this->createScreens();
        $this->createSlots();
        $this->createPlaybacks();
    }

    public function down()
    {
        $this->forge->dropTable('sound_effect_playbacks', true);
        $this->forge->dropTable('sound_effect_slots', true);
        $this->forge->dropTable('sound_effect_screens', true);
        $this->forge->dropTable('campaign_sound_effect_settings', true);
    }

    private function createSettings(): void
    {
        if ($this->db->tableExists('campaign_sound_effect_settings')) {
            return;
        }
        $this->forge->addField([
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'revision' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 1],
            'updated_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('campaign_id', true);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('updated_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('campaign_sound_effect_settings');
    }

    private function createScreens(): void
    {
        if ($this->db->tableExists('sound_effect_screens')) {
            return;
        }
        $this->forge->addField([
            'id' => $this->bigId(),
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'label' => ['type' => 'VARCHAR', 'constraint' => 12],
            'name' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'updated_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'label']);
        $this->forge->addKey(['campaign_id', 'sort_order', 'id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('updated_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('sound_effect_screens');
    }

    private function createSlots(): void
    {
        if ($this->db->tableExists('sound_effect_slots')) {
            return;
        }
        $this->forge->addField([
            'id' => $this->bigId(),
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'screen_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'slot_position' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true],
            'audio_track_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'icon' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'waveform'],
            'color' => ['type' => 'CHAR', 'constraint' => 7, 'default' => '#b98a45'],
            'shortcut' => ['type' => 'VARCHAR', 'constraint' => 24, 'null' => true],
            'volume' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 1],
            'play_mode' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'once'],
            'loop_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'fade_in_ms' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'fade_out_ms' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'stop_others' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'audience_scope' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'all'],
            'recipient_user_ids_json' => ['type' => 'JSON', 'null' => true],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'updated_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['screen_id', 'slot_position']);
        $this->forge->addKey(['campaign_id', 'shortcut']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('screen_id', 'sound_effect_screens', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('audio_track_id', 'audio_tracks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('updated_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('sound_effect_slots');
    }

    private function createPlaybacks(): void
    {
        if ($this->db->tableExists('sound_effect_playbacks')) {
            return;
        }
        $this->forge->addField([
            'playback_id' => ['type' => 'VARCHAR', 'constraint' => 128],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'slot_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'audio_track_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'started_at_ms' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'execute_at_ms' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'duration_seconds' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'null' => true],
            'volume' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 1],
            'loop_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'fade_in_ms' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'fade_out_ms' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'audience_scope' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'all'],
            'recipient_user_ids_json' => ['type' => 'JSON', 'null' => true],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'stopped_at_ms' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('playback_id', true);
        $this->forge->addKey(['campaign_id', 'stopped_at_ms', 'execute_at_ms']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('slot_id', 'sound_effect_slots', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('audio_track_id', 'audio_tracks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('sound_effect_playbacks');
    }

    private function bigId(): array
    {
        return [
            'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
            'auto_increment' => true,
        ];
    }
}
