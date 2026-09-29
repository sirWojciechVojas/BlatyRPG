<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** World-scoped, versioned lore documents. Compendium data is deliberately
 * separate from campaign journals: many campaigns may share one universe. */
final class CreateCompendium extends Migration
{
    public function up()
    {
        $this->worlds();
        $this->editors();
        $this->calendars();
        $this->calendarMonths();
        $this->calendarEras();
        $this->entryTypes();
        $this->tags();
        $this->entries();
        $this->versions();
        $this->versionTags();
        $this->versionRelations();
        $this->assets();
        $this->assetReferences();
        $this->materializations();
        $this->bootstrapWorlds();
    }

    public function down()
    {
        foreach (['compendium_materializations', 'compendium_asset_references',
            'compendium_assets', 'compendium_version_relations', 'compendium_version_tags',
            'compendium_entry_versions', 'compendium_entries', 'compendium_tags',
            'compendium_entry_types', 'compendium_calendar_eras',
            'compendium_calendar_months', 'compendium_calendars',
            'compendium_editors', 'compendium_worlds'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function id(): array
    {
        return ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
            'auto_increment' => true];
    }

    private function timestamps(bool $deleted = false): array
    {
        $fields = [
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ];
        if ($deleted) $fields['deleted_at'] = ['type' => 'DATETIME', 'null' => true];
        return $fields;
    }

    private function worlds(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'universe_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'owner_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'storage_limit_bytes' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
                'default' => 524288000],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('universe_id');
        $this->forge->addKey('owner_user_id');
        $this->forge->addForeignKey('universe_id', 'rpg_universes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('owner_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('compendium_worlds', true);
    }

    private function editors(): void
    {
        $this->forge->addField([
            'world_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'granted_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey(['world_id', 'user_id'], true);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('world_id', 'compendium_worlds', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('granted_by_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('compendium_editors', true);
    }

    private function calendars(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'world_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => 'Calendar'],
            'structure_locked' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('world_id');
        $this->forge->addForeignKey('world_id', 'compendium_worlds', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('compendium_calendars', true);
    }

    private function calendarMonths(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'calendar_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'days' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'sort_order' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['calendar_id', 'sort_order']);
        $this->forge->addForeignKey('calendar_id', 'compendium_calendars', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('compendium_calendar_months', true);
    }

    private function calendarEras(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'calendar_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'abbreviation' => ['type' => 'VARCHAR', 'constraint' => 20],
            'epoch_ordinal' => ['type' => 'BIGINT', 'constraint' => 20],
            'direction' => ['type' => 'SMALLINT', 'constraint' => 2, 'default' => 1],
            'sort_order' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['calendar_id', 'abbreviation']);
        $this->forge->addForeignKey('calendar_id', 'compendium_calendars', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('compendium_calendar_eras', true);
    }

    private function entryTypes(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'world_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 64],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'icon' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'book'],
            'is_builtin' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'field_schema_json' => ['type' => 'JSON', 'null' => false],
            'sort_order' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['world_id', 'code']);
        $this->forge->addForeignKey('world_id', 'compendium_worlds', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('compendium_entry_types', true);
    }

    private function tags(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'world_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'color' => ['type' => 'CHAR', 'constraint' => 7, 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['world_id', 'name']);
        $this->forge->addForeignKey('world_id', 'compendium_worlds', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('compendium_tags', true);
    }

    private function entries(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'world_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 180],
            'draft_version_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'published_version_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'active'],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
        ], $this->timestamps(true)));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['world_id', 'slug']);
        $this->forge->addKey(['world_id', 'published_version_id', 'status']);
        $this->forge->addForeignKey('world_id', 'compendium_worlds', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('compendium_entries', true);
    }

    private function versions(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'entry_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'version_number' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'state' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'draft'],
            'type_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'parent_entry_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 180],
            'aliases_json' => ['type' => 'JSON', 'null' => false],
            'excerpt' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'visibility' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'players'],
            'public_content_json' => ['type' => 'JSON', 'null' => false],
            'gm_content_json' => ['type' => 'JSON', 'null' => false],
            'public_fields_json' => ['type' => 'JSON', 'null' => false],
            'gm_fields_json' => ['type' => 'JSON', 'null' => false],
            'chronology_json' => ['type' => 'JSON', 'null' => true],
            'start_ordinal' => ['type' => 'BIGINT', 'constraint' => 20, 'null' => true],
            'end_ordinal' => ['type' => 'BIGINT', 'constraint' => 20, 'null' => true],
            'stat_blocks_json' => ['type' => 'JSON', 'null' => false],
            'public_search_text' => ['type' => 'TEXT', 'null' => true],
            'gm_search_text' => ['type' => 'TEXT', 'null' => true],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'published_at' => ['type' => 'DATETIME', 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addKey(['entry_id', 'state', 'version_number']);
        $this->forge->addKey(['type_id', 'start_ordinal', 'end_ordinal']);
        $this->forge->addForeignKey('entry_id', 'compendium_entries', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('type_id', 'compendium_entry_types', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('parent_entry_id', 'compendium_entries', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('compendium_entry_versions', true);
    }

    private function versionTags(): void
    {
        $this->forge->addField([
            'version_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'tag_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
        ]);
        $this->forge->addKey(['version_id', 'tag_id'], true);
        $this->forge->addKey('tag_id');
        $this->forge->addForeignKey('version_id', 'compendium_entry_versions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tag_id', 'compendium_tags', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('compendium_version_tags', true);
    }

    private function versionRelations(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'version_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'target_entry_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'label' => ['type' => 'VARCHAR', 'constraint' => 180],
            'audience' => ['type' => 'VARCHAR', 'constraint' => 8, 'default' => 'public'],
            'sort_order' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['version_id', 'target_entry_id', 'audience']);
        $this->forge->addForeignKey('version_id', 'compendium_entry_versions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('target_entry_id', 'compendium_entries', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('compendium_version_relations', true);
    }

    private function assets(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'world_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'uploaded_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'storage_key' => ['type' => 'VARCHAR', 'constraint' => 255],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 100],
            'byte_size' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'sha256' => ['type' => 'CHAR', 'constraint' => 64],
        ], $this->timestamps(true)));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('storage_key');
        $this->forge->addKey(['world_id', 'deleted_at']);
        $this->forge->addForeignKey('world_id', 'compendium_worlds', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('uploaded_by_user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('compendium_assets', true);
    }

    private function assetReferences(): void
    {
        $this->forge->addField([
            'asset_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'version_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'audience' => ['type' => 'VARCHAR', 'constraint' => 8],
        ]);
        $this->forge->addKey(['asset_id', 'version_id', 'audience'], true);
        $this->forge->addKey('version_id');
        $this->forge->addForeignKey('asset_id', 'compendium_assets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('version_id', 'compendium_entry_versions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('compendium_asset_references', true);
    }

    private function materializations(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'version_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'character_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'token_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'version_id']);
        $this->forge->addForeignKey('version_id', 'compendium_entry_versions', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('token_id', 'scene_tokens', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('compendium_materializations', true);
    }

    private function bootstrapWorlds(): void
    {
        $now = date('Y-m-d H:i:s');
        $configuredQuota = getenv('COMPENDIUM_WORLD_QUOTA_BYTES');
        $quota = ctype_digit((string) $configuredQuota) ? max(1, (int) $configuredQuota) : 524288000;
        foreach ($this->db->table('rpg_universes')->select('id')->get()->getResultArray() as $universe) {
            $this->db->table('compendium_worlds')->ignore(true)->insert([
                'universe_id' => (int) $universe['id'], 'owner_user_id' => null,
                'storage_limit_bytes' => $quota, 'revision' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
}
