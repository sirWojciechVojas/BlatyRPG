<?php

namespace App\Services\Shop;

use App\Database\Seeds\Data\WfrpData;
use CodeIgniter\Database\BaseConnection;

/**
 * Imports the legacy WFRP character inventory into the current container
 * domain. The mapping table makes reruns safe and preserves every old slot.
 */
final class LegacyCharacterInventoryImporter
{
    private const SOURCE = 'blatyrpg-old-wfrp';

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    public static function templatePayload(
        array $legacy,
        string $currencyCode = 'wfrp_empire',
        ?array $weapon = null
    ): array
    {
        return [
            'name' => (string) $legacy[1],
            'description' => (string) $legacy[2],
            'details' => (string) $legacy[3],
            'item_class' => strtoupper((string) $legacy[4]),
            'item_id' => (string) $legacy[5],
            'item_genre' => strtolower((string) $legacy[6]),
            'img_class' => strtolower((string) $legacy[7]),
            'prize' => max(0, (int) $legacy[8]),
            'currency_code' => $currencyCode,
            'charge' => max(0, (int) $legacy[9]),
            'draft' => 0,
            'weapon_json' => $weapon === null ? null : json_encode($weapon, JSON_UNESCAPED_UNICODE),
            'attributes_json' => json_encode([]),
            'mechanics_json' => json_encode([]),
            'mechanics_mode' => 'EXTEND',
        ];
    }

    /**
     * Maintains the weapon object shape used by the legacy Bountify client.
     */
    public static function weaponPayload(array $legacy): array
    {
        return [
            'ID' => (int) ($legacy[0] ?? 0),
            'NAME' => (string) ($legacy[1] ?? ''),
            'DESCRIPTION' => (string) ($legacy[2] ?? ''),
            'TYPE' => (string) ($legacy[3] ?? ''),
            'HANDED' => (string) ($legacy[4] ?? ''),
            'CATEGORY' => (string) ($legacy[5] ?? ''),
            'DICE' => (string) ($legacy[6] ?? ''),
            'MODIFIER' => (string) ($legacy[7] ?? ''),
            'PRIZE_ZK' => (int) ($legacy[8] ?? 0),
            'LOAD' => (string) ($legacy[9] ?? ''),
            'DAMAGE' => (string) ($legacy[10] ?? ''),
            'RELOAD' => (string) ($legacy[11] ?? ''),
            'RANGE' => (string) ($legacy[12] ?? ''),
            'QUALITIES' => (string) ($legacy[13] ?? ''),
            'FEATURES_ID' => (string) ($legacy[14] ?? ''),
            'OCCU_CHANCE' => (int) ($legacy[15] ?? 0),
        ];
    }

    public static function instancePayload(array $assignment, array $legacy): array
    {
        $name = trim((string) $assignment[3]);
        $description = trim((string) $assignment[4]);
        $price = $assignment[5] === null ? null : max(0, (int) $assignment[5]);
        $quantity = max(1, (int) $assignment[6]);
        $slot = trim((string) $assignment[2]);

        return [
            'name_override' => $name !== '' ? $name : (string) $legacy[1],
            'note' => $description !== '' ? $description : (string) $legacy[2],
            'data_override_json' => json_encode([
                'LEGACY_INVENTORY_ID' => (int) $assignment[0],
                'LEGACY_SOURCE' => self::SOURCE,
                'SLOT' => $slot,
                'ITEM_PLACE' => $slot,
                'QUANTITY' => $quantity,
                'PERSONAL_PSEU' => $name,
                'PERSONAL_DESC' => $description,
                'PERSONAL_COST' => $price,
                'OWNER_OPT' => (int) $assignment[7],
                'OWNER' => (int) $assignment[8],
            ], JSON_UNESCAPED_UNICODE),
        ];
    }

    public function import(): array
    {
        if (!$this->ready()) {
            return ['templates' => 0, 'instances' => 0, 'skipped' => 0];
        }

        $definitions = [];
        $weapons = [];
        foreach (WfrpData::getEquipment() as $item) {
            $definitions[(int) $item[0]] = $item;
        }
        foreach (WfrpData::getWeapons() as $weapon) {
            $weaponId = (int) ($weapon[0] ?? 0);
            if ($weaponId > 0 && !isset($weapons[$weaponId])) {
                $weapons[$weaponId] = self::weaponPayload($weapon);
            }
        }
        $characters = $this->charactersById();
        $result = ['templates' => 0, 'instances' => 0, 'skipped' => 0];

        foreach (WfrpData::getEquipmentBg() as $assignment) {
            $legacyInventoryId = (int) $assignment[0];
            $legacyEquipmentId = (int) $assignment[1];
            $characterId = (int) $assignment[8];
            if ($assignment[11] !== null || $characterId < 1
                || !isset($definitions[$legacyEquipmentId], $characters[$characterId])) {
                $result['skipped']++;
                continue;
            }
            foreach ($this->campaignIdsFor($characters[$characterId]) as $campaignId) {
                if ($this->alreadyImported($campaignId, $legacyInventoryId)) {
                    continue;
                }
                $templateId = $this->templateIdFor(
                    $campaignId,
                    $legacyEquipmentId,
                    $definitions[$legacyEquipmentId],
                    (string) ($characters[$characterId]['primary_currency_code'] ?? ''),
                    strtoupper((string) ($definitions[$legacyEquipmentId][4] ?? '')) === 'WEAPON'
                        ? ($weapons[$legacyEquipmentId] ?? null)
                        : null
                );
                if ($templateId === 0) {
                    $result['templates']++;
                    $templateId = $this->lastInsertId();
                }
                $ownerCode = 'CHAR_' . $characterId;
                $containerId = $this->characterContainerId($campaignId, $ownerCode, $characters[$characterId]);
                $payload = self::instancePayload($assignment, $definitions[$legacyEquipmentId]);
                $payload += [
                    'campaign_id' => $campaignId,
                    'template_id' => $templateId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                $this->db->table('shop_item_instances')->insert($payload);
                $instanceId = $this->lastInsertId();
                $now = date('Y-m-d H:i:s');
                $this->db->table('shop_container_instance_items')->insert([
                    'campaign_id' => $campaignId,
                    'container_id' => $containerId,
                    'instance_id' => $instanceId,
                    'price_override' => $assignment[5] === null ? null : max(0, (int) $assignment[5]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->db->table('legacy_character_inventory_imports')->insert([
                    'campaign_id' => $campaignId,
                    'character_id' => $characterId,
                    'legacy_equipment_id' => $legacyEquipmentId,
                    'legacy_inventory_id' => $legacyInventoryId,
                    'template_id' => $templateId,
                    'instance_id' => $instanceId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->ensureOwnerClaim($campaignId, $characters[$characterId], $ownerCode);
                $result['instances']++;
            }
        }
        return $result;
    }

    public function remove(): void
    {
        if (!$this->db->tableExists('legacy_character_inventory_imports')) {
            return;
        }
        $rows = $this->db->table('legacy_character_inventory_imports')->get()->getResultArray();
        foreach ($rows as $row) {
            $instanceId = (int) $row['instance_id'];
            $this->db->table('shop_container_instance_items')->where('instance_id', $instanceId)->delete();
            $this->db->table('shop_item_instances')->where('id', $instanceId)->delete();
        }
        $templateIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['template_id'],
            $rows
        )));
        foreach ($templateIds as $templateId) {
            $inUse = $this->db->table('shop_item_instances')->where('template_id', $templateId)->countAllResults();
            if ($inUse === 0) {
                $this->db->table('shop_templates')->where('id', $templateId)->delete();
            }
        }
    }

    private function ready(): bool
    {
        foreach ([
            'campaigns',
            'characters',
            'shop_templates',
            'shop_containers',
            'shop_item_instances',
            'shop_container_instance_items',
            'legacy_character_inventory_imports',
        ] as $table) {
            if (!$this->db->tableExists($table)) {
                return false;
            }
        }
        return true;
    }

    private function charactersById(): array
    {
        $rows = $this->db->table('characters')->get()->getResultArray();
        return array_reduce($rows, static function (array $carry, array $character): array {
            $carry[(int) $character['id']] = $character;
            return $carry;
        }, []);
    }

    private function campaignIdsFor(array $character): array
    {
        $ids = [];
        if (!empty($character['campaign_id'])) {
            $ids[] = (int) $character['campaign_id'];
        }
        if ($this->db->tableExists('character_campaigns')) {
            $rows = $this->db->table('character_campaigns')
                ->select('campaign_id')
                ->where('character_id', (int) $character['id'])
                ->get()->getResultArray();
            foreach ($rows as $row) {
                $ids[] = (int) $row['campaign_id'];
            }
        }
        $campaigns = $this->db->table('campaigns')
            ->select('id')
            ->where('deleted_at', null)
            ->where("LOWER(system_type) IN ('wfrp2ed', 'wfrp', 'warhammer')", null, false);
        if ($ids) {
            $campaigns->whereIn('id', array_values(array_unique(array_filter($ids))));
        }
        $campaigns = $campaigns
            ->get()->getResultArray();
        return array_values(array_unique(array_map(
            static fn (array $campaign): int => (int) $campaign['id'],
            $campaigns
        )));
    }

    private function alreadyImported(int $campaignId, int $legacyInventoryId): bool
    {
        return $this->db->table('legacy_character_inventory_imports')
            ->where('campaign_id', $campaignId)
            ->where('legacy_inventory_id', $legacyInventoryId)
            ->countAllResults() > 0;
    }

    private function templateIdFor(
        int $campaignId,
        int $legacyEquipmentId,
        array $legacy,
        string $currencyCode,
        ?array $weapon
    ): int {
        $row = $this->db->table('legacy_character_inventory_imports')
            ->select('template_id')
            ->where('campaign_id', $campaignId)
            ->where('legacy_equipment_id', $legacyEquipmentId)
            ->get()->getRowArray();
        if ($row) {
            return (int) $row['template_id'];
        }
        $payload = self::templatePayload(
            $legacy,
            $currencyCode !== '' ? $currencyCode : 'wfrp_empire',
            $weapon
        );
        $payload += [
            'campaign_id' => $campaignId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $this->db->table('shop_templates')->insert($payload);
        return 0;
    }

    private function characterContainerId(int $campaignId, string $ownerCode, array $character): int
    {
        $existing = $this->db->table('shop_containers')
            ->select('id')
            ->where('campaign_id', $campaignId)
            ->where('container_type', 'CHARACTER')
            ->where('owner_code', $ownerCode)
            ->get()->getRowArray();
        if ($existing) {
            return (int) $existing['id'];
        }
        $now = date('Y-m-d H:i:s');
        $this->db->table('shop_containers')->insert([
            'campaign_id' => $campaignId,
            'shop_id' => null,
            'container_type' => 'CHARACTER',
            'system_key' => null,
            'owner_code' => $ownerCode,
            'name' => 'Ekwipunek: ' . (string) $character['name'],
            'capacity' => null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->lastInsertId();
    }

    private function ensureOwnerClaim(int $campaignId, array $character, string $ownerCode): void
    {
        $userId = (int) ($character['user_id'] ?? 0);
        if ($userId < 1 || !$this->db->tableExists('shop_owner_claims')) {
            return;
        }
        $existing = $this->db->table('shop_owner_claims')
            ->where('campaign_id', $campaignId)
            ->where('user_id', $userId)
            ->where('owner_code', $ownerCode)
            ->get()->getRowArray();
        if ($existing) {
            return;
        }
        $payload = [
            'campaign_id' => $campaignId,
            'user_id' => $userId,
            'owner_code' => $ownerCode,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($this->db->fieldExists('character_id', 'shop_owner_claims')) {
            $payload['character_id'] = (int) $character['id'];
        }
        $this->db->table('shop_owner_claims')->insert($payload);
    }

    private function lastInsertId(): int
    {
        return (int) $this->db->insertID();
    }
}
