<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCharacterAssetSets extends Migration
{
    private const TYPES = ['avatar', 'portrait', 'token', 'fullbody'];

    public function up()
    {
        $this->createAssetSetsTable();
        $this->createAssetsTable();
        $this->addCharacterRelation();
        $this->migrateLegacyCharacterImages();
    }

    private function createAssetSetsTable(): void
    {
        if ($this->db->tableExists('character_asset_sets')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'available'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status', false, false, 'idx_character_asset_sets_status');
        $this->forge->createTable('character_asset_sets', true);
    }

    private function createAssetsTable(): void
    {
        if ($this->db->tableExists('character_assets')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'asset_set_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'type' => ['type' => 'VARCHAR', 'constraint' => 16],
            'public_id' => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['asset_set_id', 'type'], 'uq_character_assets_set_type');
        $this->forge->addForeignKey(
            'asset_set_id',
            'character_asset_sets',
            'id',
            'CASCADE',
            'CASCADE',
            'fk_character_assets_set'
        );
        $this->forge->createTable('character_assets', true);
    }

    private function addCharacterRelation(): void
    {
        if (!$this->db->fieldExists('asset_set_id', 'characters')) {
            $this->forge->addColumn('characters', [
                'asset_set_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'avatar',
                ],
            ]);
        }

        $indexes = $this->db->getIndexData('characters');
        if (!array_key_exists('uq_characters_asset_set', $indexes)) {
            $this->db->query(
                'CREATE UNIQUE INDEX `uq_characters_asset_set` ON `characters` (`asset_set_id`)'
            );
        }

        $foreignKeys = $this->db->getForeignKeyData('characters');
        $hasForeignKey = false;
        foreach ($foreignKeys as $foreignKey) {
            if (($foreignKey->constraint_name ?? '') === 'fk_characters_asset_set') {
                $hasForeignKey = true;
                break;
            }
        }
        if (!$hasForeignKey) {
            $this->db->query(
                'ALTER TABLE `characters` ADD CONSTRAINT `fk_characters_asset_set` '
                . 'FOREIGN KEY (`asset_set_id`) REFERENCES `character_asset_sets` (`id`) '
                . 'ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }
    }

    private function migrateLegacyCharacterImages(): void
    {
        $characters = $this->db->table('characters')
            ->select('id, name, avatar, avatar_url, asset_set_id')
            ->where('asset_set_id', null)
            ->get()
            ->getResultArray();
        $now = date('Y-m-d H:i:s');

        foreach ($characters as $character) {
            $legacyValue = trim((string) ($character['avatar'] ?: $character['avatar_url']));
            $publicId = $this->extractPublicId($legacyValue);
            if ($publicId === '') {
                continue;
            }

            $this->db->table('character_asset_sets')->insert([
                'name' => (string) ($character['name'] ?: ('Character ' . $character['id'])),
                'status' => 'assigned',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $assetSetId = (int) $this->db->insertID();

            foreach (self::TYPES as $type) {
                $this->db->table('character_assets')->insert([
                    'asset_set_id' => $assetSetId,
                    'type' => $type,
                    // Legacy records only contain one image. Reusing it for all
                    // four roles preserves the current UI until dedicated files
                    // are uploaded under the canonical set path.
                    'public_id' => $publicId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->db->table('characters')->where('id', (int) $character['id'])->update([
                'asset_set_id' => $assetSetId,
                // Keep both legacy columns as a temporary compatibility mirror,
                // but never retain a complete Cloudinary URL in either column.
                'avatar' => $publicId,
                'avatar_url' => $publicId,
            ]);
        }
    }

    private function extractPublicId(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $value)) {
            $path = rawurldecode((string) parse_url($value, PHP_URL_PATH));
            $marker = '/image/upload/';
            $position = strpos($path, $marker);
            if ($position === false) {
                return '';
            }
            $value = substr($path, $position + strlen($marker));
            $segments = array_values(array_filter(explode('/', trim($value, '/'))));
            foreach ($segments as $index => $segment) {
                if (preg_match('/^v\d+$/', $segment)) {
                    $segments = array_slice($segments, $index + 1);
                    break;
                }
            }
            $value = implode('/', $segments);
        }

        $value = trim(rawurldecode($value), '/');
        return (string) preg_replace('/\.(?:avif|gif|jpe?g|png|webp)$/i', '', $value);
    }

    public function down()
    {
        if ($this->db->fieldExists('asset_set_id', 'characters')) {
            try {
                $this->forge->dropForeignKey('characters', 'fk_characters_asset_set');
            } catch (\Throwable $e) {
                // Supports databases where the constraint was not created yet.
            }
            $indexes = $this->db->getIndexData('characters');
            if (array_key_exists('uq_characters_asset_set', $indexes)) {
                $this->db->query('DROP INDEX `uq_characters_asset_set` ON `characters`');
            }
            $this->forge->dropColumn('characters', 'asset_set_id');
        }
        $this->forge->dropTable('character_assets', true);
        $this->forge->dropTable('character_asset_sets', true);
    }
}
