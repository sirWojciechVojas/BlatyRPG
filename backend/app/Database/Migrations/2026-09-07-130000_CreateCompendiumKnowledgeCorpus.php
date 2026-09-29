<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Source-preserving knowledge graph for the world Compendium.
 *
 * The existing compendium_entries tables remain the editorial/publication
 * surface.  These tables keep immutable source material, stable entities and
 * campaign-owned state separate from that surface.
 */
final class CreateCompendiumKnowledgeCorpus extends Migration
{
    public function up()
    {
        $this->sources();
        $this->importRuns();
        $this->sourceDocuments();
        $this->sourceRevisions();
        $this->entities();
        $this->names();
        $this->categories();
        $this->entityCategories();
        $this->wikiLinks();
        $this->assertions();
        $this->relations();
        $this->mechanicalProfiles();
        $this->assets();
        $this->entityAssets();
        $this->campaignInstances();
        $this->campaignReveals();
        $this->campaignNotes();
        $this->userActivity();
        $this->importItems();
        $this->fullTextIndex();
    }

    public function down()
    {
        foreach ([
            'compendium_import_items',
            'compendium_user_activity',
            'compendium_campaign_notes',
            'compendium_campaign_reveals',
            'compendium_campaign_instances',
            'compendium_entity_assets',
            'compendium_corpus_assets',
            'compendium_mechanical_profiles',
            'compendium_semantic_relations',
            'compendium_assertions',
            'compendium_wiki_links',
            'compendium_entity_categories',
            'compendium_source_categories',
            'compendium_entity_names',
            'compendium_entities',
            'compendium_source_revisions',
            'compendium_source_documents',
            'compendium_import_runs',
            'compendium_sources',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function id(): array
    {
        return [
            'type' => 'BIGINT',
            'constraint' => 20,
            'unsigned' => true,
            'auto_increment' => true,
        ];
    }

    private function timestamps(bool $deleted = false): array
    {
        $fields = [
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ];
        if ($deleted) {
            $fields['deleted_at'] = ['type' => 'DATETIME', 'null' => true];
        }
        return $fields;
    }

    private function sources(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'source_key' => ['type' => 'VARCHAR', 'constraint' => 100],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 32],
            'name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'language' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pl'],
            'edition' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'base_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'license_status' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'unknown'],
            'attribution_status' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'unknown'],
            'metadata_json' => ['type' => 'JSON', 'null' => false],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('source_key');
        $this->forge->createTable('compendium_sources');
    }

    private function importRuns(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'world_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'source_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'pack_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'pack_sha256' => ['type' => 'CHAR', 'constraint' => 64],
            'mode' => ['type' => 'VARCHAR', 'constraint' => 24],
            'status' => ['type' => 'VARCHAR', 'constraint' => 24],
            'checkpoint_line' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'expected_records' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'processed_records' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'added_records' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'updated_records' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'skipped_records' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'error_records' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'report_json' => ['type' => 'JSON', 'null' => false],
            'started_at' => ['type' => 'DATETIME', 'null' => false],
            'finished_at' => ['type' => 'DATETIME', 'null' => true],
            'rolled_back_at' => ['type' => 'DATETIME', 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addKey(['world_id', 'status', 'created_at']);
        $this->forge->addKey(['pack_sha256', 'status']);
        $this->forge->addForeignKey('world_id', 'compendium_worlds', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('source_id', 'compendium_sources', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('compendium_import_runs');
    }

    private function sourceDocuments(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'source_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'external_id' => ['type' => 'VARCHAR', 'constraint' => 128],
            'source_uri' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'current_revision_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['source_id', 'external_id']);
        $this->forge->addKey('current_revision_id');
        $this->forge->addForeignKey('source_id', 'compendium_sources', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('compendium_source_documents');
    }

    private function sourceRevisions(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'document_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'import_run_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'entry_version_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'external_revision_id' => ['type' => 'VARCHAR', 'constraint' => 128],
            'title' => ['type' => 'VARCHAR', 'constraint' => 180],
            'checksum' => ['type' => 'CHAR', 'constraint' => 64],
            'source_timestamp' => ['type' => 'DATETIME', 'null' => true],
            'contributor' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'raw_wikitext' => ['type' => 'LONGTEXT', 'null' => false],
            'sanitized_html' => ['type' => 'LONGTEXT', 'null' => false],
            'plain_text' => ['type' => 'LONGTEXT', 'null' => false],
            'summary' => ['type' => 'TEXT', 'null' => true],
            'sections_json' => ['type' => 'JSON', 'null' => false],
            'quality_flags_json' => ['type' => 'JSON', 'null' => false],
            'source_payload_json' => ['type' => 'JSON', 'null' => false],
            'validation_status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'validated'],
            'published_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['document_id', 'external_revision_id', 'checksum']);
        $this->forge->addKey(['document_id', 'published_at']);
        $this->forge->addKey('import_run_id');
        $this->forge->addForeignKey('document_id', 'compendium_source_documents', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('import_run_id', 'compendium_import_runs', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('entry_version_id', 'compendium_entry_versions', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('compendium_source_revisions');
    }

    private function entities(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'world_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'entry_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'source_document_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'current_source_revision_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'canonical_id' => ['type' => 'CHAR', 'constraint' => 43],
            'type_code' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'lore'],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 180],
            'name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'normalized_name' => ['type' => 'VARCHAR', 'constraint' => 191],
            'aliases_normalized' => ['type' => 'TEXT', 'null' => true],
            'search_text_normalized' => ['type' => 'LONGTEXT', 'null' => true],
            'player_search_normalized' => ['type' => 'TEXT', 'null' => true],
            'player_description' => ['type' => 'LONGTEXT', 'null' => true],
            'gm_notes' => ['type' => 'LONGTEXT', 'null' => true],
            'visibility' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'gm'],
            'spoiler_level' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'unreviewed'],
            'editorial_status' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'source_preserved_not_proofread'],
            'verification_status' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'unverified'],
            'canon_status' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'unreviewed'],
            'edition' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
        ], $this->timestamps(true)));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['world_id', 'canonical_id']);
        $this->forge->addUniqueKey(['world_id', 'entry_id']);
        $this->forge->addUniqueKey(['world_id', 'source_document_id']);
        $this->forge->addUniqueKey(['world_id', 'slug']);
        $this->forge->addKey(['world_id', 'type_code', 'visibility']);
        $this->forge->addKey(['world_id', 'normalized_name']);
        $this->forge->addForeignKey('world_id', 'compendium_worlds', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('entry_id', 'compendium_entries', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('source_document_id', 'compendium_source_documents', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('current_source_revision_id', 'compendium_source_revisions', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('compendium_entities');
    }

    private function names(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'locale' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pl'],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 24],
            'name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'normalized_name' => ['type' => 'VARCHAR', 'constraint' => 191],
            'source_document_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['entity_id', 'locale', 'kind', 'normalized_name']);
        $this->forge->addKey(['normalized_name', 'kind']);
        $this->forge->addForeignKey('entity_id', 'compendium_entities', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('source_document_id', 'compendium_source_documents', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('compendium_entity_names');
    }

    private function categories(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'source_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 180],
            'normalized_name' => ['type' => 'VARCHAR', 'constraint' => 191],
            'source_count' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['source_id', 'normalized_name']);
        $this->forge->addKey('name');
        $this->forge->addForeignKey('source_id', 'compendium_sources', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('compendium_source_categories');
    }

    private function entityCategories(): void
    {
        $this->forge->addField([
            'entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'category_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
        ]);
        $this->forge->addKey(['entity_id', 'category_id'], true);
        $this->forge->addKey('category_id');
        $this->forge->addForeignKey('entity_id', 'compendium_entities', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('category_id', 'compendium_source_categories', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('compendium_entity_categories');
    }

    private function wikiLinks(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'source_revision_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'from_entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'target_entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'target_external_id' => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'target_title' => ['type' => 'VARCHAR', 'constraint' => 180],
            'anchor' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'unresolved'],
            'link_type' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'wiki_link'],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addKey(['from_entity_id', 'source_revision_id']);
        $this->forge->addKey(['target_entity_id', 'status']);
        $this->forge->addKey('target_external_id');
        $this->forge->addForeignKey('source_revision_id', 'compendium_source_revisions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('from_entity_id', 'compendium_entities', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('target_entity_id', 'compendium_entities', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('compendium_wiki_links');
    }

    private function assertions(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'assertion_key' => ['type' => 'VARCHAR', 'constraint' => 128],
            'entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'predicate' => ['type' => 'VARCHAR', 'constraint' => 100],
            'value_json' => ['type' => 'JSON', 'null' => false],
            'source_revision_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'citation_json' => ['type' => 'JSON', 'null' => false],
            'edition' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'proposed'],
            'visibility' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'gm'],
            'valid_time_json' => ['type' => 'JSON', 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('assertion_key');
        $this->forge->addKey(['entity_id', 'predicate', 'status']);
        $this->forge->addForeignKey('entity_id', 'compendium_entities', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('source_revision_id', 'compendium_source_revisions', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('compendium_assertions');
    }

    private function relations(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'relation_key' => ['type' => 'VARCHAR', 'constraint' => 128],
            'subject_entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'predicate' => ['type' => 'VARCHAR', 'constraint' => 100],
            'object_entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'source_revision_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'citation_json' => ['type' => 'JSON', 'null' => false],
            'edition' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'proposed'],
            'canon_status' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'unreviewed'],
            'visibility' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'gm'],
            'valid_time_json' => ['type' => 'JSON', 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('relation_key');
        $this->forge->addUniqueKey(['subject_entity_id', 'predicate', 'object_entity_id', 'relation_key']);
        $this->forge->addKey(['subject_entity_id', 'predicate']);
        $this->forge->addKey(['object_entity_id', 'predicate']);
        $this->forge->addForeignKey('subject_entity_id', 'compendium_entities', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('object_entity_id', 'compendium_entities', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('source_revision_id', 'compendium_source_revisions', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('compendium_semantic_relations');
    }

    private function mechanicalProfiles(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'profile_key' => ['type' => 'VARCHAR', 'constraint' => 128],
            'entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'system_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 40],
            'variant' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'default'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'custom_unverified'],
            'usable' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'profile_json' => ['type' => 'JSON', 'null' => false],
            'source_json' => ['type' => 'JSON', 'null' => false],
            'linked_resource_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'linked_resource_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('profile_key');
        $this->forge->addKey(['entity_id', 'system_id', 'usable']);
        $this->forge->addForeignKey('entity_id', 'compendium_entities', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('system_id', 'rpg_systems', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('compendium_mechanical_profiles');
    }

    private function assets(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'source_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'asset_key' => ['type' => 'VARCHAR', 'constraint' => 128],
            'filename' => ['type' => 'VARCHAR', 'constraint' => 255],
            'source_titles_json' => ['type' => 'JSON', 'null' => false],
            'source_url' => ['type' => 'VARCHAR', 'constraint' => 700, 'null' => true],
            'author' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'license' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'role' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'image'],
            'download_status' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'not_downloaded'],
            'checksum' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'storage_key' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'byte_size' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'error_message' => ['type' => 'TEXT', 'null' => true],
            'metadata_json' => ['type' => 'JSON', 'null' => false],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['source_id', 'asset_key']);
        $this->forge->addKey(['download_status', 'updated_at']);
        $this->forge->addForeignKey('source_id', 'compendium_sources', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('compendium_corpus_assets');
    }

    private function entityAssets(): void
    {
        $this->forge->addField([
            'entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'asset_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'source_revision_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'sort_order' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addKey(['entity_id', 'asset_id'], true);
        $this->forge->addKey('asset_id');
        $this->forge->addForeignKey('entity_id', 'compendium_entities', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('asset_id', 'compendium_corpus_assets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('source_revision_id', 'compendium_source_revisions', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('compendium_entity_assets');
    }

    private function campaignInstances(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'source_revision_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 32],
            'target_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'target_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'state_json' => ['type' => 'JSON', 'null' => false],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
        ], $this->timestamps(true)));
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'entity_id', 'kind']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('entity_id', 'compendium_entities', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('source_revision_id', 'compendium_source_revisions', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('compendium_campaign_instances');
    }

    private function campaignReveals(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'source_revision_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'section_keys_json' => ['type' => 'JSON', 'null' => true],
            'snapshot_search_text' => ['type' => 'LONGTEXT', 'null' => false],
            'granted_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'entity_id', 'user_id', 'revoked_at']);
        $this->forge->addKey(['source_revision_id', 'revoked_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('entity_id', 'compendium_entities', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('source_revision_id', 'compendium_source_revisions', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('granted_by_user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('compendium_campaign_reveals');
    }

    private function campaignNotes(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'author_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'visibility' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'gm'],
            'body' => ['type' => 'LONGTEXT', 'null' => false],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
        ], $this->timestamps(true)));
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'entity_id', 'visibility', 'deleted_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('entity_id', 'compendium_entities', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('author_user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('compendium_campaign_notes');
    }

    private function userActivity(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'is_favorite' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'last_read_at' => ['type' => 'DATETIME', 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['user_id', 'campaign_id', 'entity_id']);
        $this->forge->addKey(['user_id', 'is_favorite', 'updated_at']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('entity_id', 'compendium_entities', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('compendium_user_activity');
    }

    private function importItems(): void
    {
        $this->forge->addField(array_merge([
            'id' => $this->id(),
            'import_run_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'line_number' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'external_id' => ['type' => 'VARCHAR', 'constraint' => 128],
            'entity_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'source_revision_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'previous_source_revision_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'entry_version_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'previous_entry_version_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 24],
            'status' => ['type' => 'VARCHAR', 'constraint' => 24],
            'error_message' => ['type' => 'TEXT', 'null' => true],
        ], $this->timestamps()));
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['import_run_id', 'external_id']);
        $this->forge->addKey(['import_run_id', 'status', 'line_number']);
        $this->forge->addForeignKey('import_run_id', 'compendium_import_runs', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('entity_id', 'compendium_entities', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('source_revision_id', 'compendium_source_revisions', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('compendium_import_items');
    }

    private function fullTextIndex(): void
    {
        if (strtolower((string) $this->db->DBDriver) !== 'mysqli') {
            return;
        }
        $table = $this->db->prefixTable('compendium_entities');
        $this->db->query(
            "ALTER TABLE `{$table}` ADD FULLTEXT INDEX `ft_compendium_entities_search` "
            . '(`normalized_name`, `aliases_normalized`, `search_text_normalized`)'
        );
    }
}
