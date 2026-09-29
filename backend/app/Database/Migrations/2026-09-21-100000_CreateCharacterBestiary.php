<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCharacterBestiary extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('character_bestiary_encounters')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
                'auto_increment' => true,
            ],
            'campaign_id' => [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
            ],
            'character_id' => [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
            ],
            'entry_id' => [
                'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
            ],
            'first_seen_scene_id' => [
                'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
                'null' => true,
            ],
            'first_seen_token_id' => [
                'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
                'null' => true,
            ],
            'discovered_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(
            ['campaign_id', 'character_id', 'entry_id'],
            'uq_character_bestiary_entry'
        );
        $this->forge->addKey(
            ['character_id', 'discovered_at'],
            false,
            false,
            'idx_character_bestiary_discovered'
        );
        $this->forge->addForeignKey(
            'campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE'
        );
        $this->forge->addForeignKey(
            'character_id', 'characters', 'id', 'CASCADE', 'CASCADE'
        );
        $this->forge->addForeignKey(
            'entry_id', 'compendium_entries', 'id', 'CASCADE', 'CASCADE'
        );
        $this->forge->addForeignKey(
            'first_seen_scene_id', 'scenes', 'id', 'CASCADE', 'SET NULL'
        );
        $this->forge->addForeignKey(
            'first_seen_token_id', 'scene_tokens', 'id', 'CASCADE', 'SET NULL'
        );
        $this->forge->createTable('character_bestiary_encounters');
    }

    public function down()
    {
        $this->forge->dropTable('character_bestiary_encounters', true);
    }
}
