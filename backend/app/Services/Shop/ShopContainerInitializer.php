<?php

namespace App\Services\Shop;

use App\Models\ShopContainerModel;
use App\Models\ShopModel;

final class ShopContainerInitializer
{
    private $containerModel;
    private $shopModel;

    public function __construct()
    {
        $this->containerModel = new ShopContainerModel();
        $this->shopModel = new ShopModel();
    }

    public function ensureAndMap(int $campaignId, string $ownerCode, ?array $shops = null): array
    {
        $ownerCode = strtoupper($ownerCode ?: 'BG1');
        $containers = $this->containerModel
            ->where('campaign_id', $campaignId)
            ->orderBy('id', 'ASC')
            ->findAll();
        if ($shops === null) {
            $shops = $this->shopModel
                ->where('campaign_id', $campaignId)
                ->where('deleted_at', null)
                ->findAll();
        }
        $known = $this->knownContainers($containers);
        $missing = $this->missingBaseContainers($campaignId, $ownerCode, $known);

        foreach ($shops as $shop) {
            $shopId = (int) $shop['id'];
            if (isset($known['shop'][$shopId])) {
                continue;
            }
            $missing[] = [
                'campaign_id' => $campaignId,
                'shop_id' => $shopId,
                'container_type' => 'SHOP',
                'system_key' => null,
                'owner_code' => null,
                'name' => 'Sklep '.$shopId.' - Asortyment',
                'capacity' => null,
                'is_active' => 1,
            ];
        }
        if ($missing) {
            $this->containerModel->insertBatch($missing);
            $containers = $this->containerModel
                ->where('campaign_id', $campaignId)
                ->orderBy('id', 'ASC')
                ->findAll();
        }
        return $this->map($containers, $ownerCode);
    }

    private function knownContainers(array $containers): array
    {
        $known = ['system' => [], 'character' => [], 'trash' => [], 'shop' => []];
        foreach ($containers as $container) {
            $type = strtoupper((string) $container['container_type']);
            $systemKey = strtoupper((string) ($container['system_key'] ?? ''));
            $ownerCode = strtoupper((string) ($container['owner_code'] ?? ''));
            if ($type === 'SYSTEM' && $systemKey) {
                $known['system'][$systemKey] = true;
            } elseif ($type === 'CHARACTER') {
                $known['character'][$ownerCode] = true;
            } elseif ($type === 'TRASH') {
                $known['trash'][$ownerCode] = true;
            } elseif ($type === 'SHOP' && !empty($container['shop_id'])) {
                $known['shop'][(int) $container['shop_id']] = true;
            }
        }
        return $known;
    }

    private function missingBaseContainers(int $campaignId, string $ownerCode, array $known): array
    {
        $definitions = [
            ['SYSTEM', 'DEFAULT', null, 'DEFAULT', null, 'system', 'DEFAULT'],
            ['SYSTEM', 'TRASH', null, 'TRASH', null, 'system', 'TRASH'],
            ['CHARACTER', null, $ownerCode, $ownerCode.' - Ekwipunek', null, 'character', $ownerCode],
            ['TRASH', null, $ownerCode, $ownerCode.' - Kosz', 16, 'trash', $ownerCode],
        ];
        $missing = [];
        foreach ($definitions as [$type, $systemKey, $owner, $name, $capacity, $group, $key]) {
            if (isset($known[$group][$key])) {
                continue;
            }
            $missing[] = [
                'campaign_id' => $campaignId,
                'shop_id' => null,
                'container_type' => $type,
                'system_key' => $systemKey,
                'owner_code' => $owner,
                'name' => $name,
                'capacity' => $capacity,
                'is_active' => 1,
            ];
        }
        return $missing;
    }

    private function map(array $containers, string $ownerCode): array
    {
        $containers = array_values(array_filter($containers, static fn (array $container): bool => (int) ($container['is_active'] ?? 1) === 1));
        $map = ['DEFAULT' => null, 'TRASH' => null, 'CHARACTER' => null, 'OWNER_TRASH' => null, 'SHOP_BY_ID' => [], 'ALL' => $containers];
        foreach ($containers as $container) {
            $id = (int) $container['id'];
            $type = strtoupper((string) $container['container_type']);
            $systemKey = strtoupper((string) ($container['system_key'] ?? ''));
            $owner = strtoupper((string) ($container['owner_code'] ?? ''));
            if ($type === 'SYSTEM' && $systemKey === 'DEFAULT') $map['DEFAULT'] = $id;
            elseif ($type === 'SYSTEM' && $systemKey === 'TRASH') $map['TRASH'] = $id;
            elseif ($type === 'CHARACTER' && $owner === $ownerCode) $map['CHARACTER'] = $id;
            elseif ($type === 'TRASH' && $owner === $ownerCode) $map['OWNER_TRASH'] = $id;
            elseif ($type === 'SHOP' && !empty($container['shop_id'])) $map['SHOP_BY_ID'][(int) $container['shop_id']] = $id;
        }
        return $map;
    }
}
