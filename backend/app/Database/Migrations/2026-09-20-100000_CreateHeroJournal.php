<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHeroJournal extends Migration
{
    public function up()
    {
        $this->createEntries();
        $this->createSections();
        $this->createChecklistItems();
        $this->createRelations();
        $this->createNpcEncounters();
    }

    public function down()
    {
        $this->forge->dropTable('hero_journal_npc_encounters', true);
        $this->forge->dropTable('hero_journal_relations', true);
        $this->forge->dropTable('hero_journal_checklist_items', true);
        $this->forge->dropTable('hero_journal_sections', true);
        $this->forge->dropTable('hero_journal_entries', true);
    }

    private function createEntries(): void
    {
        if ($this->db->tableExists('hero_journal_entries')) {
            return;
        }
        $this->forge->addField([
            'id' => $this->idField(),
            'campaign_id' => $this->unsignedInt(),
            'character_id' => $this->unsignedInt(),
            'owner_user_id' => $this->unsignedInt(),
            'author_user_id' => $this->unsignedInt(),
            'entry_type' => ['type' => 'VARCHAR', 'constraint' => 24],
            'title' => ['type' => 'VARCHAR', 'constraint' => 180],
            'status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'in_progress'],
            'summary' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'session_number' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'occurred_on' => ['type' => 'DATE', 'null' => true],
            'visibility' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'private'],
            'trust_level' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'null' => true],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'archived_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'character_id', 'entry_type']);
        $this->forge->addKey(['owner_user_id', 'visibility']);
        $this->forge->addKey(['character_id', 'archived_at', 'updated_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('owner_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('author_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('hero_journal_entries');
    }

    private function createSections(): void
    {
        if ($this->db->tableExists('hero_journal_sections')) {
            return;
        }
        $this->forge->addField([
            'id' => $this->idField(),
            'entry_id' => $this->unsignedInt(),
            'section_key' => ['type' => 'VARCHAR', 'constraint' => 48],
            'content' => ['type' => 'TEXT', 'null' => true],
            'visibility' => ['type' => 'VARCHAR', 'constraint' => 24, 'null' => true],
            'sort_order' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['entry_id', 'section_key']);
        $this->forge->addKey(['entry_id', 'sort_order']);
        $this->forge->addForeignKey('entry_id', 'hero_journal_entries', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('hero_journal_sections');
    }

    private function createChecklistItems(): void
    {
        if ($this->db->tableExists('hero_journal_checklist_items')) {
            return;
        }
        $this->forge->addField([
            'id' => $this->idField(),
            'entry_id' => $this->unsignedInt(),
            'label' => ['type' => 'VARCHAR', 'constraint' => 300],
            'is_completed' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 0],
            'sort_order' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['entry_id', 'sort_order']);
        $this->forge->addForeignKey('entry_id', 'hero_journal_entries', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('hero_journal_checklist_items');
    }

    private function createRelations(): void
    {
        if ($this->db->tableExists('hero_journal_relations')) {
            return;
        }
        $this->forge->addField([
            'id' => $this->idField(),
            'source_entry_id' => $this->unsignedInt(),
            'target_entry_id' => $this->unsignedInt(),
            'relation_type' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'related'],
            'created_by_user_id' => $this->unsignedInt(),
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['source_entry_id', 'target_entry_id', 'relation_type']);
        $this->forge->addKey('target_entry_id');
        $this->forge->addForeignKey('source_entry_id', 'hero_journal_entries', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('target_entry_id', 'hero_journal_entries', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('hero_journal_relations');
    }

    private function createNpcEncounters(): void
    {
        if ($this->db->tableExists('hero_journal_npc_encounters')) {
            return;
        }
        $this->forge->addField([
            'id' => $this->idField(),
            'entry_id' => $this->unsignedInt(),
            'session_number' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'occurred_on' => ['type' => 'DATE', 'null' => true],
            'summary' => ['type' => 'TEXT'],
            'sort_order' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['entry_id', 'sort_order']);
        $this->forge->addForeignKey('entry_id', 'hero_journal_entries', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('hero_journal_npc_encounters');
    }

    private function idField(): array
    {
        return [
            'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
            'auto_increment' => true,
        ];
    }

    private function unsignedInt(): array
    {
        return ['type' => 'INT', 'constraint' => 10, 'unsigned' => true];
    }
}
