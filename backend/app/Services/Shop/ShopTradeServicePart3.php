<?php

namespace App\Services\Shop;

use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopTradeTransactionModel;
use App\Models\ShopModel;

trait ShopTradeServicePart3
{
    private function canForcePrices(array $payload, array $authContext): bool
    {
        $role = strtolower((string) ($authContext['role'] ?? ''));
        return !empty($payload['forcedPrices']) && in_array($role, ['gm', 'admin'], true);
    }

    private function trustedCondition(array $selection, array $authContext, array $placements = []): string
    {
        $role = strtolower((string) ($authContext['role'] ?? ''));
        if (!in_array($role, ['gm', 'admin'], true)) {
            $meta = $placements[0]['data_override_json'] ?? [];
            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?: [];
            }
            $condition = strtolower((string) ($meta['CONDITION'] ?? 'good'));
            return in_array($condition, ['ruined', 'poor', 'worn', 'good', 'excellent'], true)
                ? $condition
                : 'good';
        }
        $condition = strtolower((string) ($selection['condition'] ?? 'good'));
        return in_array($condition, ['ruined', 'poor', 'worn', 'good', 'excellent'], true)
            ? $condition
            : 'good';
    }

    private function containerStateForAuth(int $campaignId, string $ownerCode, array $authContext): array
    {
        $role = strtolower((string) ($authContext['role'] ?? ''));
        return in_array($role, ['gm', 'admin'], true)
            ? $this->containerService->getContainers($campaignId, $ownerCode)
            : $this->containerService->getContainersForOwner($campaignId, $ownerCode);
    }

    private function characterCarryLimit(): int
    {
        $configured = filter_var(getenv('SHOP_CHARACTER_CARRY_LIMIT'), FILTER_VALIDATE_INT);
        return $configured !== false && $configured > 0 ? (int) $configured : 300;
    }

    private function currentContainerCharge(int $campaignId, int $containerId): int
    {
        $templateRows = $this->db->table('shop_container_template_items rows')
            ->select('rows.quantity, templates.charge')
            ->join('shop_templates templates', 'templates.id = rows.template_id')
            ->where('rows.campaign_id', $campaignId)
            ->where('rows.container_id', $containerId)
            ->get()
            ->getResultArray();
        $total = 0;
        foreach ($templateRows as $row) {
            $total += max(0, (int) ($row['charge'] ?? 0)) * max(0, (int) ($row['quantity'] ?? 0));
        }
        $instances = $this->db->table('shop_container_instance_items placements')
            ->select('instances.data_override_json, templates.charge')
            ->join('shop_item_instances instances', 'instances.id = placements.instance_id')
            ->join('shop_templates templates', 'templates.id = instances.template_id')
            ->where('placements.campaign_id', $campaignId)
            ->where('placements.container_id', $containerId)
            ->get()
            ->getResultArray();
        foreach ($instances as $instance) {
            $meta = json_decode((string) ($instance['data_override_json'] ?? ''), true) ?: [];
            $total += max(0, (int) ($meta['CHARGE'] ?? $instance['charge'] ?? 0));
        }
        return $total;
    }

    private function findInstancePlacements(
        int $campaignId,
        int $containerId,
        int $templateId,
        int $quantity,
        int $instanceId,
        array $excludedInstanceIds
    ): array {
        $builder = $this->db->table('shop_container_instance_items placements')
            ->select('placements.id AS placement_id, placements.instance_id, placements.price_override, instances.data_override_json')
            ->join('shop_item_instances instances', 'instances.id = placements.instance_id')
            ->where('placements.campaign_id', $campaignId)
            ->where('placements.container_id', $containerId)
            ->where('instances.campaign_id', $campaignId)
            ->where('instances.template_id', $templateId);
        if ($instanceId > 0) {
            $builder->where('placements.instance_id', $instanceId);
        }
        if ($excludedInstanceIds) {
            $builder->whereNotIn('placements.instance_id', array_values(array_unique(array_map('intval', $excludedInstanceIds))));
        }

        return $builder
            ->orderBy('placements.id', 'ASC')
            ->limit(max(1, $quantity))
            ->get()
            ->getResultArray();
    }

    private function countTemplateInstances(int $campaignId, int $containerId, int $templateId): int
    {
        return (int) $this->db->table('shop_container_instance_items placements')
            ->join('shop_item_instances instances', 'instances.id = placements.instance_id')
            ->where('placements.campaign_id', $campaignId)
            ->where('placements.container_id', $containerId)
            ->where('instances.campaign_id', $campaignId)
            ->where('instances.template_id', $templateId)
            ->countAllResults();
    }
}
