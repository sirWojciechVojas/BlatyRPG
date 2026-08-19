<?php

namespace App\Database\Migrations;

use App\Models\CharacterAssetModel;
use CodeIgniter\Database\Migration;

class MapTelaesinCloudinaryAssetSet extends Migration
{
    private const ASSET_SET_ID = 1;
    private const LEGACY_PUBLIC_ID = 'Telaesin_g6hfpk';

    public function up()
    {
        if (!$this->db->tableExists('character_asset_sets')) {
            return;
        }

        $telaesin = $this->findTelaesin();
        if (!$telaesin) {
            // On a fresh installation characters are inserted by the seeder
            // after migrations. CharacterLegacySeeder reserves set 000001.
            return;
        }

        $this->db->transBegin();
        try {
            $canonicalSet = $this->db->query(
                'SELECT id, name, status FROM character_asset_sets WHERE id = ? FOR UPDATE',
                [self::ASSET_SET_ID]
            )->getRowArray();
            $occupants = $this->db->query(
                'SELECT id FROM characters WHERE asset_set_id = ? FOR UPDATE',
                [self::ASSET_SET_ID]
            )->getResultArray();

            $otherOccupants = array_values(array_filter(
                $occupants,
                static fn (array $row): bool => (int) $row['id'] !== (int) $telaesin['id']
            ));
            if ($canonicalSet && $otherOccupants) {
                $relocatedSetId = $this->cloneSet($canonicalSet);
                $this->db->table('characters')
                    ->where('asset_set_id', self::ASSET_SET_ID)
                    ->where('id !=', (int) $telaesin['id'])
                    ->update([
                        'asset_set_id' => $relocatedSetId,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }

            if (!$canonicalSet) {
                $this->db->table('character_asset_sets')->insert([
                    'id' => self::ASSET_SET_ID,
                    'name' => 'Tel Aes In',
                    'status' => 'available',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->writeCanonicalAssets();
            $this->db->table('character_asset_sets')->where('id', self::ASSET_SET_ID)->update([
                'name' => 'Tel Aes In',
                'status' => 'assigned',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $formerSetId = (int) ($telaesin['asset_set_id'] ?? 0);
            $this->db->table('characters')->where('id', (int) $telaesin['id'])->update([
                'asset_set_id' => self::ASSET_SET_ID,
                'avatar' => $this->publicId('avatar'),
                'avatar_url' => $this->publicId('avatar'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if ($formerSetId > 0 && $formerSetId !== self::ASSET_SET_ID) {
                // Preserve the legacy Cloudinary mapping as a disabled backup.
                $this->db->table('character_asset_sets')->where('id', $formerSetId)->update([
                    'status' => 'disabled',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Could not map Telaesin Cloudinary assets.');
            }
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }

    private function cloneSet(array $sourceSet): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('character_asset_sets')->insert([
            'name' => $sourceSet['name'],
            'status' => 'assigned',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $newSetId = (int) $this->db->insertID();
        $assets = $this->db->table('character_assets')
            ->where('asset_set_id', self::ASSET_SET_ID)
            ->get()
            ->getResultArray();
        foreach ($assets as $asset) {
            $this->db->table('character_assets')->insert([
                'asset_set_id' => $newSetId,
                'type' => $asset['type'],
                'public_id' => $asset['public_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        return $newSetId;
    }

    private function writeCanonicalAssets(): void
    {
        $now = date('Y-m-d H:i:s');
        foreach (CharacterAssetModel::TYPES as $type) {
            $existing = $this->db->table('character_assets')
                ->select('id')
                ->where('asset_set_id', self::ASSET_SET_ID)
                ->where('type', $type)
                ->get()
                ->getRowArray();
            $data = ['public_id' => $this->publicId($type), 'updated_at' => $now];
            if ($existing) {
                $this->db->table('character_assets')->where('id', (int) $existing['id'])->update($data);
            } else {
                $this->db->table('character_assets')->insert(array_merge($data, [
                    'asset_set_id' => self::ASSET_SET_ID,
                    'type' => $type,
                    'created_at' => $now,
                ]));
            }
        }
    }

    private function findTelaesin(): ?array
    {
        $row = $this->db->table('characters AS character_row')
            ->select('character_row.id, character_row.asset_set_id')
            ->join('character_assets AS asset', 'asset.asset_set_id = character_row.asset_set_id', 'left')
            ->groupStart()
                ->where('asset.public_id', self::LEGACY_PUBLIC_ID)
                ->orWhere('character_row.avatar', self::LEGACY_PUBLIC_ID)
                ->orWhere('character_row.name', 'Tel Aes In')
            ->groupEnd()
            ->limit(1)
            ->get()
            ->getRowArray();
        return $row ?: null;
    }

    private function publicId(string $type): string
    {
        return sprintf('character-assets/%06d/%s', self::ASSET_SET_ID, $type);
    }

    public function down()
    {
        $telaesin = $this->db->table('characters')->where('asset_set_id', self::ASSET_SET_ID)->get()->getRowArray();
        $legacyAsset = $this->db->table('character_assets')
            ->where('public_id', self::LEGACY_PUBLIC_ID)
            ->where('asset_set_id !=', self::ASSET_SET_ID)
            ->get()
            ->getRowArray();
        if (!$telaesin || !$legacyAsset) {
            return;
        }
        $legacySetId = (int) $legacyAsset['asset_set_id'];
        $this->db->table('characters')->where('id', (int) $telaesin['id'])->update([
            'asset_set_id' => $legacySetId,
            'avatar' => self::LEGACY_PUBLIC_ID,
            'avatar_url' => self::LEGACY_PUBLIC_ID,
        ]);
        $this->db->table('character_asset_sets')->where('id', $legacySetId)->update(['status' => 'assigned']);
        $this->db->table('character_asset_sets')->where('id', self::ASSET_SET_ID)->update(['status' => 'available']);
    }
}
