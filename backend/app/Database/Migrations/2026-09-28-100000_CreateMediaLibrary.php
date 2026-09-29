<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateMediaLibrary extends Migration
{
    private const DOMAIN_TABLES = [
        'audio_tracks',
        'map_assets',
        'handout_assets',
        'compendium_assets',
        'compendium_corpus_assets',
        'token_template_assets',
        'profession_assets',
    ];

    public function up()
    {
        if (!$this->db->tableExists('media_assets')) {
            return;
        }

        $this->extendMediaAssets();
        $this->createCollections();
        $this->createSceneAssetLinks();
        foreach (self::DOMAIN_TABLES as $table) {
            $this->linkDomainTable($table);
        }
        $this->normalizeLegacyMedia();
    }

    public function down()
    {
        foreach (array_reverse(self::DOMAIN_TABLES) as $table) {
            if (!$this->db->tableExists($table)
                || !$this->db->fieldExists('media_asset_id', $table)) {
                continue;
            }
            $foreignKey = 'fk_' . $table . '_media_asset';
            if ($this->foreignKeyExists($table, $foreignKey)) {
                $this->forge->dropForeignKey($table, $foreignKey);
            }
            $index = 'idx_' . $table . '_media_asset';
            if (isset($this->db->getIndexData($table)[$index])) {
                $this->db->query('DROP INDEX `' . $index . '` ON `' . $table . '`');
            }
            $this->forge->dropColumn($table, 'media_asset_id');
        }

        $this->forge->dropTable('scene_media_assets', true);
        $this->forge->dropTable('media_collection_assets', true);
        $this->forge->dropTable('media_collections', true);

        $indexes = $this->db->getIndexData('media_assets');
        foreach (['idx_media_assets_library', 'idx_media_assets_format'] as $index) {
            if (isset($indexes[$index])) {
                $this->db->query('DROP INDEX `' . $index . '` ON `media_assets`');
            }
        }
        foreach (['name', 'description', 'tags', 'custom_metadata', 'format', 'revision'] as $field) {
            if ($this->db->fieldExists($field, 'media_assets')) {
                $this->forge->dropColumn('media_assets', $field);
            }
        }
    }

    private function extendMediaAssets(): void
    {
        $fields = [
            'name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'category'],
            'description' => ['type' => 'TEXT', 'null' => true, 'after' => 'name'],
            'tags' => ['type' => 'JSON', 'null' => true, 'after' => 'description'],
            'custom_metadata' => ['type' => 'JSON', 'null' => true, 'after' => 'metadata'],
            'format' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true, 'after' => 'mime_type'],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1, 'after' => 'status'],
        ];
        foreach ($fields as $name => $definition) {
            if (!$this->db->fieldExists($name, 'media_assets')) {
                $this->forge->addColumn('media_assets', [$name => $definition]);
            }
        }

        $indexes = $this->db->getIndexData('media_assets');
        if (!isset($indexes['idx_media_assets_library'])) {
            $this->db->query(
                'CREATE INDEX `idx_media_assets_library` '
                . 'ON `media_assets` (`status`, `category`, `resource_type`, `created_at`)'
            );
        }
        if (!isset($indexes['idx_media_assets_format'])) {
            $this->db->query('CREATE INDEX `idx_media_assets_format` ON `media_assets` (`format`)');
        }
    }

    private function createCollections(): void
    {
        if (!$this->db->tableExists('media_collections')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'owner_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'name' => ['type' => 'VARCHAR', 'constraint' => 150],
                'description' => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['owner_user_id', 'name'], 'uq_media_collections_owner_name');
            $this->forge->addForeignKey('owner_user_id', 'users', 'id', 'CASCADE', 'CASCADE', 'fk_media_collections_owner');
            $this->forge->createTable('media_collections');
        }

        if (!$this->db->tableExists('media_collection_assets')) {
            $this->forge->addField([
                'collection_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'media_asset_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'added_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey(['collection_id', 'media_asset_id'], true);
            $this->forge->addKey('media_asset_id', false, false, 'idx_media_collection_assets_asset');
            $this->forge->addForeignKey('collection_id', 'media_collections', 'id', 'CASCADE', 'CASCADE', 'fk_media_collection_assets_collection');
            $this->forge->addForeignKey('media_asset_id', 'media_assets', 'id', 'CASCADE', 'CASCADE', 'fk_media_collection_assets_asset');
            $this->forge->addForeignKey('added_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL', 'fk_media_collection_assets_user');
            $this->forge->createTable('media_collection_assets');
        }
    }

    private function createSceneAssetLinks(): void
    {
        if ($this->db->tableExists('scene_media_assets')) {
            return;
        }
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'legacy_key' => ['type' => 'VARCHAR', 'constraint' => 255],
            'media_asset_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'legacy_key'], 'uq_scene_media_assets_legacy');
        $this->forge->addKey('media_asset_id', false, false, 'idx_scene_media_assets_media');
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE', 'fk_scene_media_assets_campaign');
        $this->forge->addForeignKey('media_asset_id', 'media_assets', 'id', 'CASCADE', 'RESTRICT', 'fk_scene_media_assets_media');
        $this->forge->createTable('scene_media_assets');
    }

    private function linkDomainTable(string $table): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }
        if (!$this->db->fieldExists('media_asset_id', $table)) {
            $this->forge->addColumn($table, [
                'media_asset_id' => [
                    'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true,
                ],
            ]);
        }
        $index = 'idx_' . $table . '_media_asset';
        if (!isset($this->db->getIndexData($table)[$index])) {
            $this->db->query('CREATE INDEX `' . $index . '` ON `' . $table . '` (`media_asset_id`)');
        }
        $foreignKey = 'fk_' . $table . '_media_asset';
        if (!$this->foreignKeyExists($table, $foreignKey)) {
            $this->db->query(
                'ALTER TABLE `' . $table . '` ADD CONSTRAINT `' . $foreignKey . '` '
                . 'FOREIGN KEY (`media_asset_id`) REFERENCES `media_assets` (`id`) '
                . 'ON DELETE RESTRICT ON UPDATE CASCADE'
            );
        }
    }

    private function normalizeLegacyMedia(): void
    {
        $this->db->query(
            "UPDATE `media_assets` SET `category` = 'characters' "
            . "WHERE `category` IN ('avatar', 'portrait', 'token', 'fullbody')"
        );
        $this->db->query(
            'UPDATE `media_assets` SET `name` = COALESCE(NULLIF(`original_filename`, \'\'), CONCAT(\'Asset #\', `id`)) '
            . 'WHERE `name` IS NULL OR `name` = \'\''
        );
        $this->db->query(
            "UPDATE `media_assets` SET `format` = LOWER(SUBSTRING_INDEX(`original_filename`, '.', -1)) "
            . "WHERE (`format` IS NULL OR `format` = '') AND `original_filename` LIKE '%.%'"
        );
        $this->db->query(
            "UPDATE `media_assets` SET `format` = LOWER(SUBSTRING_INDEX(`mime_type`, '/', -1)) "
            . "WHERE (`format` IS NULL OR `format` = '') AND `mime_type` LIKE '%/%'"
        );
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $row = $this->db->query(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? '
            . "AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY' LIMIT 1",
            [$table, $name]
        )->getRowArray();
        return $row !== null;
    }
}
