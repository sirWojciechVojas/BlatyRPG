<?php

namespace App\Services\Shop;

use App\Models\ShopContainerModel;
use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerTemplateItemModel;

trait ShopTradeSnapshotServicePart2
{
    private function captureInstancePlacements(int $campaignId, int $templateId, array $containerIds): array
    {
        if (!$templateId || !$containerIds) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'placementId' => (int) $row['placement_id'],
                'instanceId' => (int) $row['instance_id'],
                'containerId' => (int) $row['container_id'],
                'priceOverride' => $row['price_override'] === null ? null : (int) $row['price_override'],
            ];
        }, $this->db->table('shop_container_instance_items placements')
            ->select('placements.id AS placement_id, placements.instance_id, placements.container_id, placements.price_override')
            ->join('shop_item_instances instances', 'instances.id = placements.instance_id')
            ->where('placements.campaign_id', $campaignId)
            ->where('instances.campaign_id', $campaignId)
            ->where('instances.template_id', $templateId)
            ->whereIn('placements.container_id', $containerIds)
            ->orderBy('placements.instance_id', 'ASC')
            ->get()
            ->getResultArray());
    }

    private function normalizeInstancePlacements(array $placements): array
    {
        $normalized = array_map(static function (array $placement): string {
            return implode(':', [
                (int) ($placement['instanceId'] ?? 0),
                (int) ($placement['containerId'] ?? 0),
                $placement['priceOverride'] === null ? 'null' : (int) $placement['priceOverride'],
            ]);
        }, $placements);
        sort($normalized);

        return $normalized;
    }

    private function restoreInstanceScope(int $campaignId, array $entry): void
    {
        foreach ((array) ($entry['placements'] ?? []) as $placement) {
            $placement = (array) $placement;
            $instanceId = (int) ($placement['instanceId'] ?? 0);
            if (!$instanceId) {
                continue;
            }
            $existing = $this->containerInstanceItemModel
                ->where('campaign_id', $campaignId)
                ->where('instance_id', $instanceId)
                ->first();
            if (!$existing) {
                continue;
            }
            $this->containerInstanceItemModel->update((int) $existing['id'], [
                'container_id' => (int) ($placement['containerId'] ?? 0),
                'price_override' => $placement['priceOverride'] === null
                    ? null
                    : (int) $placement['priceOverride'],
            ]);
        }
    }

    private function restoreItemEntry(int $campaignId, array $entry): void
    {
        $containerId = (int) ($entry['containerId'] ?? 0);
        $templateId = (int) ($entry['templateId'] ?? 0);
        if (!$containerId || !$templateId) {
            return;
        }

        $existing = $this->containerTemplateItemModel
            ->where('campaign_id', $campaignId)
            ->where('container_id', $containerId)
            ->where('template_id', $templateId)
            ->first();
        $shouldExist = (bool) ($entry['exists'] ?? false);
        $quantity = $entry['quantity'];

        if (!$shouldExist || ($quantity !== null && (int) $quantity <= 0)) {
            if ($existing) {
                $this->containerTemplateItemModel->delete((int) $existing['id']);
            }
            return;
        }

        $payload = [
            'campaign_id' => $campaignId,
            'container_id' => $containerId,
            'template_id' => $templateId,
            'quantity' => $quantity === null ? null : max(1, (int) $quantity),
            'price_override' => $entry['priceOverride'] === null ? null : (int) $entry['priceOverride'],
        ];

        if ($existing) {
            $this->containerTemplateItemModel->update((int) $existing['id'], $payload);
            return;
        }

        $this->containerTemplateItemModel->insert($payload);
    }

    private function error(string $code, int $status): array
    {
        return [
            'ok' => false,
            'code' => $code,
            'status' => $status,
        ];
    }
}
