<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Versioned map projects, immutable revisions, assets, AI jobs and VTT publication provenance. */
class CreateMapBuilder extends Migration
{
    public function up()
    {
        $this->createProjects();
        $this->createRevisions();
        $this->createAssets();
        $this->createAiJobs();
        $this->extendSceneElements();
    }

    public function down()
    {
        $this->dropSceneElementColumns('scene_lights');
        $this->dropSceneElementColumns('scene_walls');
        $this->dropSceneElementColumns('scene_tiles');
        $this->forge->dropTable('map_ai_jobs', true);
        $this->forge->dropTable('map_assets', true);
        $this->forge->dropTable('map_revisions', true);
        $this->forge->dropTable('map_projects', true);
    }

    private function createProjects(): void
    {
        if ($this->db->tableExists('map_projects')) return;
        $this->forge->addField([
            'id' => $this->bigId(),
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'scene_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'is_template' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'current_revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'published_revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'published_at' => ['type' => 'DATETIME', 'null' => true],
            'lock_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'lock_session_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'lock_client_id' => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'lock_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'updated_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'deleted_at', 'updated_at']);
        $this->forge->addKey(['campaign_id', 'scene_id', 'deleted_at']);
        $this->forge->addKey(['lock_expires_at', 'lock_user_id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('scene_id', 'scenes', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('lock_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('lock_session_id', 'auth_sessions', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('updated_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('map_projects');
    }

    private function createRevisions(): void
    {
        if ($this->db->tableExists('map_revisions')) return;
        $this->forge->addField([
            'id' => $this->bigId(),
            'map_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'revision_number' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'document_json' => ['type' => 'LONGTEXT'],
            'document_bytes' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'checksum_sha256' => ['type' => 'CHAR', 'constraint' => 64],
            'summary' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['map_id', 'revision_number']);
        $this->forge->addKey(['map_id', 'created_at']);
        $this->forge->addForeignKey('map_id', 'map_projects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('map_revisions');
    }

    private function createAssets(): void
    {
        if ($this->db->tableExists('map_assets')) return;
        $this->forge->addField([
            'id' => $this->bigId(),
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'stable_id' => ['type' => 'VARCHAR', 'constraint' => 128],
            'version' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'category' => ['type' => 'VARCHAR', 'constraint' => 80],
            'tags_json' => ['type' => 'JSON', 'null' => true],
            'metadata_json' => ['type' => 'JSON', 'null' => true],
            'storage_key' => ['type' => 'VARCHAR', 'constraint' => 100],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 64],
            'byte_size' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'width' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'height' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'origin' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'upload'],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'stable_id', 'version']);
        $this->forge->addUniqueKey('storage_key');
        $this->forge->addKey(['campaign_id', 'category', 'deleted_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('map_assets');
    }

    private function createAiJobs(): void
    {
        if ($this->db->tableExists('map_ai_jobs')) return;
        $this->forge->addField([
            'id' => ['type' => 'CHAR', 'constraint' => 36],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'map_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'requested_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 128],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 32],
            'status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'queued'],
            'prompt' => ['type' => 'TEXT'],
            'input_json' => ['type' => 'LONGTEXT', 'null' => true],
            'result_json' => ['type' => 'LONGTEXT', 'null' => true],
            'error_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'model' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'estimated_cost_units' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'actual_cost_units' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'cancel_requested' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'started_at' => ['type' => 'DATETIME', 'null' => true],
            'finished_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'requested_by_user_id', 'idempotency_key']);
        $this->forge->addKey(['status', 'created_at']);
        $this->forge->addKey(['campaign_id', 'created_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('map_id', 'map_projects', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('requested_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('map_ai_jobs');
    }

    private function extendSceneElements(): void
    {
        foreach (['scene_tiles', 'scene_walls', 'scene_lights'] as $table) {
            if (!$this->db->tableExists($table)) continue;
            if (!$this->db->fieldExists('source_map_id', $table)) {
                $this->forge->addColumn($table, [
                    'source_map_id' => [
                        'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
                        'null' => true, 'after' => 'scene_id',
                    ],
                ]);
            }
            if (!$this->db->fieldExists('source_map_revision', $table)) {
                $this->forge->addColumn($table, [
                    'source_map_revision' => [
                        'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
                        'null' => true, 'after' => 'source_map_id',
                    ],
                ]);
            }
            if (!$this->db->fieldExists('source_map_object_id', $table)) {
                $this->forge->addColumn($table, [
                    'source_map_object_id' => [
                        'type' => 'VARCHAR', 'constraint' => 128,
                        'null' => true, 'after' => 'source_map_revision',
                    ],
                ]);
            }
            $indexName = $table . '_source_map_idx';
            if (!array_key_exists($indexName, $this->db->getIndexData($table))) {
                $this->db->query(
                    'CREATE INDEX ' . $indexName . ' ON '
                    . $this->db->prefixTable($table)
                    . ' (source_map_id, source_map_revision)'
                );
            }
        }
    }

    private function dropSceneElementColumns(string $table): void
    {
        if (!$this->db->tableExists($table)) return;
        foreach (['source_map_object_id', 'source_map_revision', 'source_map_id'] as $field) {
            if ($this->db->fieldExists($field, $table)) $this->forge->dropColumn($table, $field);
        }
    }

    private function bigId(): array
    {
        return [
            'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
            'auto_increment' => true,
        ];
    }
}
