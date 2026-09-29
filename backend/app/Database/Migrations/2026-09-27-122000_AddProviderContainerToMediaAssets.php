<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProviderContainerToMediaAssets extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('media_assets')) {
            return;
        }
        if (!$this->db->fieldExists('provider_container', 'media_assets')) {
            $this->forge->addColumn('media_assets', [
                'provider_container' => [
                    'type' => 'VARCHAR',
                    'constraint' => 128,
                    'default' => '',
                    'after' => 'provider',
                ],
            ]);
        }

        $cloudName = trim((string) (getenv('CLOUDINARY_CLOUD_NAME') ?: 'dajzxmjyc'));
        $legacyBucket = trim((string) (getenv('R2_BUCKET') ?: ''));
        $privateBucket = trim((string) (getenv('R2_PRIVATE_BUCKET') ?: $legacyBucket));
        $publicBucket = trim((string) (getenv('R2_PUBLIC_BUCKET') ?: $legacyBucket));

        $this->db->table('media_assets')
            ->where('provider', 'cloudinary')
            ->where('provider_container', '')
            ->update(['provider_container' => $cloudName]);
        if ($publicBucket !== '') {
            $this->db->table('media_assets')
                ->where('provider', 'r2')
                ->where('visibility', 'public')
                ->where('provider_container', '')
                ->update(['provider_container' => $publicBucket]);
        }
        if ($privateBucket !== '') {
            $this->db->table('media_assets')
                ->where('provider', 'r2')
                ->whereIn('visibility', ['campaign', 'private'])
                ->where('provider_container', '')
                ->update(['provider_container' => $privateBucket]);
        }

        $indexes = $this->db->getIndexData('media_assets');
        if (!isset($indexes['idx_media_assets_provider_container'])) {
            $this->db->query(
                'CREATE INDEX `idx_media_assets_provider_container` '
                . 'ON `media_assets` (`provider`, `provider_container`)'
            );
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('media_assets')
            || !$this->db->fieldExists('provider_container', 'media_assets')) {
            return;
        }
        $indexes = $this->db->getIndexData('media_assets');
        if (isset($indexes['idx_media_assets_provider_container'])) {
            $this->db->query('DROP INDEX `idx_media_assets_provider_container` ON `media_assets`');
        }
        $this->forge->dropColumn('media_assets', 'provider_container');
    }
}
