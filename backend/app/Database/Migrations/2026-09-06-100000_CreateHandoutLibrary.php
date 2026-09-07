<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Private GM source material and campaign-scoped handout snapshots.
 *
 * Campaign copies deliberately use `journals`: ResourceType::JOURNAL already
 * reserves that name and a handout is the first journal document kind.
 */
class CreateHandoutLibrary extends Migration
{
    public function up()
    {
        $this->createLibraryFolders();
        $this->createLibraryTags();
        $this->createLibraryEntries();
        $this->createEntryTags();
        $this->createAssets();
        $this->createJournals();
        $this->createRecipients();
        $this->createLinks();
        $this->createNotifications();
        $this->createAssetReferences();
    }

    public function down()
    {
        $this->forge->dropTable('handout_asset_references', true);
        $this->forge->dropTable('journal_notifications', true);
        $this->forge->dropTable('journal_links', true);
        $this->forge->dropTable('journal_recipients', true);
        $this->forge->dropTable('journals', true);
        $this->forge->dropTable('handout_assets', true);
        $this->forge->dropTable('handout_library_entry_tags', true);
        $this->forge->dropTable('handout_library_entries', true);
        $this->forge->dropTable('handout_library_tags', true);
        $this->forge->dropTable('handout_library_folders', true);
    }

    private function idField(): array
    {
        return ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true];
    }

    private function timestamps(bool $softDelete = true): array
    {
        $fields = [
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ];
        if ($softDelete) $fields['deleted_at'] = ['type' => 'DATETIME', 'null' => true];
        return $fields;
    }

    private function createLibraryFolders(): void
    {
        if ($this->db->tableExists('handout_library_folders')) return;
        $this->forge->addField(array_merge([
            'id' => $this->idField(),
            'owner_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'parent_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addKey(['owner_user_id', 'parent_id', 'sort_order', 'id']);
        $this->forge->addForeignKey('owner_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('parent_id', 'handout_library_folders', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('handout_library_folders');
    }

    private function createLibraryTags(): void
    {
        if ($this->db->tableExists('handout_library_tags')) return;
        $this->forge->addField(array_merge([
            'id' => $this->idField(),
            'owner_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 80],
        ], $this->timestamps(false)));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['owner_user_id', 'name']);
        $this->forge->addForeignKey('owner_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('handout_library_tags');
    }

    private function createLibraryEntries(): void
    {
        if ($this->db->tableExists('handout_library_entries')) return;
        $this->forge->addField(array_merge([
            'id' => $this->idField(),
            'owner_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'folder_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 180],
            'content_json' => ['type' => 'JSON', 'null' => false],
            'search_text' => ['type' => 'TEXT', 'null' => true],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addKey(['owner_user_id', 'folder_id', 'updated_at']);
        $this->forge->addKey(['owner_user_id', 'deleted_at']);
        $this->forge->addForeignKey('owner_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('folder_id', 'handout_library_folders', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('handout_library_entries');
    }

    private function createEntryTags(): void
    {
        if ($this->db->tableExists('handout_library_entry_tags')) return;
        $this->forge->addField([
            'entry_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'tag_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
        ]);
        $this->forge->addKey(['entry_id', 'tag_id'], true);
        $this->forge->addKey('tag_id');
        $this->forge->addForeignKey('entry_id', 'handout_library_entries', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tag_id', 'handout_library_tags', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('handout_library_entry_tags');
    }

    private function createAssets(): void
    {
        if ($this->db->tableExists('handout_assets')) return;
        $this->forge->addField(array_merge([
            'id' => $this->idField(),
            'owner_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'storage_key' => ['type' => 'VARCHAR', 'constraint' => 255],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 100],
            'byte_size' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'sha256' => ['type' => 'CHAR', 'constraint' => 64],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addKey(['owner_user_id', 'deleted_at']);
        $this->forge->addUniqueKey('storage_key');
        $this->forge->addForeignKey('owner_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('handout_assets');
    }

    private function createJournals(): void
    {
        if ($this->db->tableExists('journals')) return;
        $this->forge->addField(array_merge([
            'id' => $this->idField(),
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'source_library_entry_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'author_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'handout'],
            'title' => ['type' => 'VARCHAR', 'constraint' => 180],
            'content_json' => ['type' => 'JSON', 'null' => false],
            'search_text' => ['type' => 'TEXT', 'null' => true],
            'tag_snapshot_json' => ['type' => 'JSON', 'null' => true],
            'folder_path_snapshot' => ['type' => 'VARCHAR', 'constraint' => 1024, 'null' => true],
            'audience_mode' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'private'],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'published_at' => ['type' => 'DATETIME', 'null' => false],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'kind', 'deleted_at', 'updated_at']);
        $this->forge->addKey(['campaign_id', 'audience_mode', 'deleted_at']);
        $this->forge->addKey('source_library_entry_id');
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('source_library_entry_id', 'handout_library_entries', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('author_user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('journals');
    }

    private function createRecipients(): void
    {
        if ($this->db->tableExists('journal_recipients')) return;
        $this->forge->addField([
            'journal_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey(['journal_id', 'user_id'], true);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('journal_id', 'journals', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('journal_recipients');
    }

    private function createLinks(): void
    {
        if ($this->db->tableExists('journal_links')) return;
        $this->forge->addField([
            'id' => $this->idField(),
            'journal_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'target_type' => ['type' => 'VARCHAR', 'constraint' => 32],
            'target_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'placement' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'mention'],
            'label' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['journal_id', 'target_type', 'target_id', 'placement']);
        $this->forge->addKey(['journal_id', 'placement']);
        $this->forge->addForeignKey('journal_id', 'journals', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('journal_links');
    }

    private function createNotifications(): void
    {
        if ($this->db->tableExists('journal_notifications')) return;
        $this->forge->addField([
            'id' => $this->idField(),
            'journal_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'batch_id' => ['type' => 'CHAR', 'constraint' => 36],
            'read_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['journal_id', 'user_id']);
        $this->forge->addKey(['user_id', 'read_at', 'updated_at']);
        $this->forge->addKey('batch_id');
        $this->forge->addForeignKey('journal_id', 'journals', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('journal_notifications');
    }

    private function createAssetReferences(): void
    {
        if ($this->db->tableExists('handout_asset_references')) return;
        $this->forge->addField([
            'id' => $this->idField(),
            'asset_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'context_type' => ['type' => 'VARCHAR', 'constraint' => 16],
            'context_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['asset_id', 'context_type', 'context_id']);
        $this->forge->addKey(['context_type', 'context_id']);
        $this->forge->addForeignKey('asset_id', 'handout_assets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('handout_asset_references');
    }
}
