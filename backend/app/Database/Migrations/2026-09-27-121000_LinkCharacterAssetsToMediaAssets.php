<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class LinkCharacterAssetsToMediaAssets extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('character_assets') || !$this->db->tableExists('media_assets')) {
            return;
        }
        if (!$this->db->fieldExists('media_asset_id', 'character_assets')) {
            $this->forge->addColumn('character_assets', [
                'media_asset_id' => [
                    'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
                    'null' => true, 'after' => 'type',
                ],
            ]);
        }

        $this->backfill();

        $this->forge->modifyColumn('character_assets', [
            'media_asset_id' => [
                'name' => 'media_asset_id', 'type' => 'BIGINT', 'constraint' => 20,
                'unsigned' => true, 'null' => false,
            ],
            'public_id' => [
                'name' => 'public_id', 'type' => 'VARCHAR', 'constraint' => 255,
                'null' => true,
            ],
        ]);

        $this->db->query(
            'CREATE INDEX `idx_character_assets_media_asset` ON `character_assets` (`media_asset_id`)'
        );
        $this->db->query(
            'ALTER TABLE `character_assets` ADD CONSTRAINT `fk_character_assets_media_asset` '
            . 'FOREIGN KEY (`media_asset_id`) REFERENCES `media_assets` (`id`) '
            . 'ON DELETE RESTRICT ON UPDATE CASCADE'
        );
    }

    public function down()
    {
        if (!$this->db->tableExists('character_assets')
            || !$this->db->fieldExists('media_asset_id', 'character_assets')) {
            return;
        }
        $rows = $this->db->table('character_assets asset')
            ->select('asset.id, media.public_id')
            ->join('media_assets media', 'media.id = asset.media_asset_id', 'left')
            ->get()->getResultArray();
        foreach ($rows as $row) {
            if (!empty($row['public_id'])) {
                $this->db->table('character_assets')->where('id', (int) $row['id'])
                    ->update(['public_id' => $row['public_id']]);
            }
        }
        $this->forge->dropForeignKey('character_assets', 'fk_character_assets_media_asset');
        $this->db->query('DROP INDEX `idx_character_assets_media_asset` ON `character_assets`');
        $this->forge->dropColumn('character_assets', 'media_asset_id');
        $this->forge->modifyColumn('character_assets', [
            'public_id' => [
                'name' => 'public_id', 'type' => 'VARCHAR', 'constraint' => 255,
                'null' => false,
            ],
        ]);
    }

    private function backfill(): void
    {
        $rows = $this->db->table('character_assets')
            ->select('id, type, public_id, created_at, updated_at')
            ->where('media_asset_id', null)
            ->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($rows as $row) {
            $publicId = $this->normalizePublicId((string) ($row['public_id'] ?? ''));
            if ($publicId === '') {
                $publicId = 'legacy/character-assets/' . (int) $row['id'];
            }
            $media = $this->db->table('media_assets')->select('id')
                ->where('provider', 'cloudinary')->where('public_id', $publicId)
                ->get()->getRowArray();
            if (!$media) {
                $now = date('Y-m-d H:i:s');
                $this->db->table('media_assets')->insert([
                    'owner_user_id' => null,
                    'campaign_id' => null,
                    'provider' => 'cloudinary',
                    'provider_asset_id' => null,
                    'public_id' => $publicId,
                    'resource_type' => 'image',
                    'category' => (string) $row['type'],
                    'mime_type' => 'image/*',
                    'visibility' => 'public',
                    'status' => 'ready',
                    'metadata' => json_encode([
                        'delivery_type' => 'upload',
                        'migrated_from' => 'character_assets',
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => $row['created_at'] ?: $now,
                    'updated_at' => $row['updated_at'] ?: $now,
                ]);
                $media = ['id' => (int) $this->db->insertID()];
            }
            $this->db->table('character_assets')->where('id', (int) $row['id'])
                ->update(['media_asset_id' => (int) $media['id']]);
        }
    }

    private function normalizePublicId(string $value): string
    {
        $value = trim($value);
        if (!preg_match('#^https?://#i', $value)) {
            return (string) preg_replace('/\.(?:avif|gif|jpe?g|png|webp)$/i', '', trim($value, '/'));
        }
        $path = rawurldecode((string) parse_url($value, PHP_URL_PATH));
        if (!preg_match('#/(?:image|video|raw)/(?:upload|authenticated)/(.+)$#', $path, $matches)) {
            return '';
        }
        $segments = array_values(array_filter(explode('/', $matches[1])));
        foreach ($segments as $index => $segment) {
            if (preg_match('/^v\d+$/', $segment)) {
                $segments = array_slice($segments, $index + 1);
                break;
            }
        }
        while ($segments && (strpos($segments[0], ',') !== false || strpos($segments[0], 'f_') === 0)) {
            array_shift($segments);
        }
        return (string) preg_replace('/\.(?:avif|gif|jpe?g|png|webp)$/i', '', implode('/', $segments));
    }
}
