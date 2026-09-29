<?php

namespace App\Database\Migrations;

use App\Services\Media\ExternalMediaUrl;
use CodeIgniter\Database\Migration;

/**
 * Makes scene tile media part of the central registry without changing the
 * legacy URL, geometry, layer or scene configuration.
 */
final class RegisterExternalSceneTileAssets extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('media_assets')) {
            return;
        }

        $this->extendMediaAssets();
        $this->linkSceneTiles();
        $this->backfillSceneTiles();
    }

    public function down()
    {
        if ($this->db->tableExists('scene_tiles')
            && $this->db->fieldExists('media_asset_id', 'scene_tiles')) {
            if ($this->foreignKeyExists('scene_tiles', 'fk_scene_tiles_media_asset')) {
                $this->forge->dropForeignKey('scene_tiles', 'fk_scene_tiles_media_asset');
            }
            if (isset($this->db->getIndexData('scene_tiles')['idx_scene_tiles_media_asset'])) {
                $this->db->query('DROP INDEX `idx_scene_tiles_media_asset` ON `scene_tiles`');
            }
            $this->forge->dropColumn('scene_tiles', 'media_asset_id');
        }
        if (!$this->db->tableExists('media_assets')) {
            return;
        }
        foreach (['source_url', 'availability_status', 'availability_checked_at'] as $field) {
            if ($this->db->fieldExists($field, 'media_assets')) {
                $this->forge->dropColumn('media_assets', $field);
            }
        }
    }

    private function extendMediaAssets(): void
    {
        $fields = [
            'source_url' => ['type' => 'VARCHAR', 'constraint' => 2048, 'null' => true],
            'availability_status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'unknown'],
            'availability_checked_at' => ['type' => 'DATETIME', 'null' => true],
        ];
        foreach ($fields as $name => $definition) {
            if (!$this->db->fieldExists($name, 'media_assets')) {
                $this->forge->addColumn('media_assets', [$name => $definition]);
            }
        }
    }

    private function linkSceneTiles(): void
    {
        if (!$this->db->tableExists('scene_tiles')) {
            return;
        }
        if (!$this->db->fieldExists('media_asset_id', 'scene_tiles')) {
            $this->forge->addColumn('scene_tiles', [
                'media_asset_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            ]);
        }
        if (!isset($this->db->getIndexData('scene_tiles')['idx_scene_tiles_media_asset'])) {
            $this->db->query('CREATE INDEX `idx_scene_tiles_media_asset` ON `scene_tiles` (`media_asset_id`)');
        }
        if (!$this->foreignKeyExists('scene_tiles', 'fk_scene_tiles_media_asset')) {
            $this->db->query(
                'ALTER TABLE `scene_tiles` ADD CONSTRAINT `fk_scene_tiles_media_asset` '
                . 'FOREIGN KEY (`media_asset_id`) REFERENCES `media_assets` (`id`) '
                . 'ON DELETE RESTRICT ON UPDATE CASCADE'
            );
        }
    }

    private function backfillSceneTiles(): void
    {
        if (!$this->db->tableExists('scene_tiles')
            || !$this->db->fieldExists('media_asset_id', 'scene_tiles')) {
            return;
        }
        $rows = $this->db->table('scene_tiles')->select('id, campaign_id, name, asset_url, media_type')
            ->where('deleted_at', null)->where('media_asset_id', null)
            ->where('asset_url IS NOT NULL', null, false)->where('asset_url !=', '')
            ->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($rows as $row) {
            $assetId = $this->linkedInternalAsset($row);
            if ($assetId === null) {
                $assetId = $this->registerExternalAsset($row);
            }
            if ($assetId !== null) {
                $this->db->table('scene_tiles')->where('id', (int) $row['id'])
                    ->where('media_asset_id', null)->update(['media_asset_id' => $assetId]);
            }
        }
    }

    private function linkedInternalAsset(array $tile): ?int
    {
        $path = (string) parse_url((string) $tile['asset_url'], PHP_URL_PATH);
        if (preg_match('#^/api/campaigns/(\d+)/maps/assets/(\d+)/file$#', $path, $matches) === 1
            && (int) $matches[1] === (int) $tile['campaign_id']
            && $this->db->tableExists('map_assets')) {
            $row = $this->db->table('map_assets')->select('media_asset_id')
                ->where('id', (int) $matches[2])->where('campaign_id', (int) $tile['campaign_id'])
                ->where('deleted_at', null)->get()->getRowArray();
            if (!empty($row['media_asset_id'])) {
                return (int) $row['media_asset_id'];
            }
        }
        if (preg_match('#^/api/campaigns/(\d+)/scene-assets/([a-f0-9]{32}\.(?:png|jpg|webp|gif))/file$#', $path, $matches) === 1
            && (int) $matches[1] === (int) $tile['campaign_id']
            && $this->db->tableExists('scene_media_assets')) {
            $row = $this->db->table('scene_media_assets')->select('media_asset_id')
                ->where('campaign_id', (int) $tile['campaign_id'])->where('legacy_key', $matches[2])
                ->get()->getRowArray();
            if (!empty($row['media_asset_id'])) {
                return (int) $row['media_asset_id'];
            }
        }
        return null;
    }

    private function registerExternalAsset(array $tile): ?int
    {
        $sourceUrl = ExternalMediaUrl::canonicalize((string) $tile['asset_url']);
        if ($sourceUrl === null) {
            return null;
        }
        $fingerprint = ExternalMediaUrl::fingerprint($sourceUrl);
        $existing = $this->db->table('media_assets')->select('id')->where('provider', 'external')
            ->where('provider_asset_id', $fingerprint)->where('deleted_at', null)->get()->getRowArray();
        if ($existing) {
            return (int) $existing['id'];
        }
        $filename = ExternalMediaUrl::filename($sourceUrl);
        $mediaType = strtolower((string) ($tile['media_type'] ?? 'image')) === 'video' ? 'video' : 'image';
        $name = trim((string) ($tile['name'] ?? ''));
        if ($name === '' || strtolower($name) === 'tile') {
            $name = pathinfo($filename, PATHINFO_FILENAME) ?: 'External map';
        }
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $format = preg_match('/^[a-z0-9]{1,32}$/', $extension) === 1 ? $extension : null;
        $campaign = $this->db->table('campaigns')->select('game_master_id')
            ->where('id', (int) $tile['campaign_id'])->get()->getRowArray();
        $now = date('Y-m-d H:i:s');
        $inserted = $this->db->table('media_assets')->insert([
            'owner_user_id' => !empty($campaign['game_master_id']) ? (int) $campaign['game_master_id'] : null,
            'campaign_id' => null,
            'provider' => 'external',
            'provider_container' => 'external',
            'provider_asset_id' => $fingerprint,
            'public_id' => null,
            'source_url' => $sourceUrl,
            'resource_type' => $mediaType,
            'category' => 'maps',
            'name' => mb_substr($name, 0, 255),
            'description' => null,
            'tags' => json_encode(['external', 'scene']),
            'original_filename' => $filename,
            'mime_type' => $mediaType === 'video' ? 'video/*' : 'image/*',
            'format' => $format,
            'file_size' => null,
            'visibility' => 'public',
            'status' => 'ready',
            'availability_status' => 'unknown',
            'availability_checked_at' => null,
            'revision' => 1,
            'metadata' => json_encode([
                'source' => 'external_url',
                'migrated_from' => 'scene_tiles',
                'availability_policy' => 'not_checked_server_side',
            ]),
            'custom_metadata' => json_encode([]),
            'upload_expires_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ($inserted) {
            return (int) $this->db->insertID();
        }
        $existing = $this->db->table('media_assets')->select('id')->where('provider', 'external')
            ->where('provider_asset_id', $fingerprint)->where('deleted_at', null)->get()->getRowArray();
        return $existing ? (int) $existing['id'] : null;
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return $this->db->query(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? '
            . "AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY' LIMIT 1",
            [$table, $name]
        )->getRowArray() !== null;
    }
}
