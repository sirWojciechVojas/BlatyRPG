<?php

namespace App\Services\Shop;

use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopItemInstanceModel;
use App\Models\ShopModel;
use App\Models\ShopTemplateModel;

trait ShopContainerServicePart2
{
    private function moveWithinTransaction(int $campaignId, array $payload): array
    {
        $fromContainerId = (int) ($payload['fromContainerId'] ?? 0);
        $toContainerId = (int) ($payload['toContainerId'] ?? 0);
        $templateId = isset($payload['templateId']) ? (int) $payload['templateId'] : null;
        $instanceId = isset($payload['instanceId']) ? (int) $payload['instanceId'] : null;
        $quantity = isset($payload['quantity']) ? (int) $payload['quantity'] : 1;

        if (!$fromContainerId || !$toContainerId || $fromContainerId === $toContainerId) {
            return ['ok' => false, 'code' => 'invalid_container', 'status' => 400];
        }
        if ($quantity <= 0) {
            return ['ok' => false, 'code' => 'invalid_quantity', 'status' => 400];
        }
        if (!$this->containersBelongToCampaign($campaignId, [$fromContainerId, $toContainerId])) {
            return ['ok' => false, 'code' => 'container_campaign_mismatch', 'status' => 403];
        }

        if ($templateId) {
            $result = $this->moveTemplateQuantity($campaignId, $templateId, $fromContainerId, $toContainerId, $quantity);
            if (!($result['ok'] ?? false)) {
                return $result;
            }
        } elseif ($instanceId) {
            if ($quantity !== 1) {
                return ['ok' => false, 'code' => 'invalid_instance_quantity', 'status' => 400];
            }
            $placement = $this->containerInstanceItemModel
                ->where('campaign_id', $campaignId)
                ->where('container_id', $fromContainerId)
                ->where('instance_id', $instanceId)
                ->first();

            if (!$placement) {
                return ['ok' => false, 'code' => 'not_found', 'status' => 404];
            }

            $this->containerInstanceItemModel->update((int) $placement['id'], ['container_id' => $toContainerId]);
        } else {
            return ['ok' => false, 'code' => 'invalid_payload', 'status' => 400];
        }

        return [
            'ok' => true,
            'changed' => [
                'fromContainerId' => $fromContainerId,
                'toContainerId' => $toContainerId,
                'templateId' => $templateId,
                'instanceId' => $instanceId,
                'quantity' => $quantity,
            ],
        ];
    }

    private function containersBelongToCampaign(int $campaignId, array $containerIds): bool
    {
        $ids = array_values(array_unique(array_map('intval', $containerIds)));
        if (!$ids) {
            return false;
        }

        $found = $this->containerModel
            ->where('campaign_id', $campaignId)
            ->whereIn('id', $ids)
            ->countAllResults();

        return $found === count($ids);
    }

    public function buyFromShop(int $campaignId, array $payload): array
    {
        return $this->move($campaignId, [
            'fromContainerId' => (int) ($payload['shopContainerId'] ?? 0),
            'toContainerId' => (int) ($payload['targetContainerId'] ?? 0),
            'templateId' => $payload['templateId'] ?? null,
            'instanceId' => $payload['instanceId'] ?? null,
            'quantity' => $payload['quantity'] ?? 1,
            'ownerCode' => strtoupper((string) ($payload['ownerCode'] ?? 'BG1')),
        ]);
    }

    public function trash(int $campaignId, array $payload): array
    {
        $ownerCode = strtoupper((string) ($payload['ownerCode'] ?? 'BG1'));
        $containerMap = $this->ensureBaseContainers($campaignId, $ownerCode);
        $target = $containerMap['OWNER_TRASH'] ?: $containerMap['TRASH'];
        if (!$target) {
            return ['ok' => false, 'code' => 'not_found', 'status' => 404];
        }

        return $this->move($campaignId, [
            'fromContainerId' => (int) ($payload['fromContainerId'] ?? 0),
            'toContainerId' => (int) $target,
            'templateId' => $payload['templateId'] ?? null,
            'instanceId' => $payload['instanceId'] ?? null,
            'quantity' => $payload['quantity'] ?? 1,
            'ownerCode' => $ownerCode,
        ]);
    }

    public function restore(int $campaignId, array $payload): array
    {
        $ownerCode = strtoupper((string) ($payload['ownerCode'] ?? 'BG1'));
        $containerMap = $this->ensureBaseContainers($campaignId, $ownerCode);
        $target = $containerMap['CHARACTER'] ?: $containerMap['DEFAULT'];
        $source = (int) ($payload['fromContainerId'] ?? ($containerMap['OWNER_TRASH'] ?: $containerMap['TRASH']));

        if (!$source || !$target) {
            return ['ok' => false, 'code' => 'not_found', 'status' => 404];
        }

        return $this->move($campaignId, [
            'fromContainerId' => $source,
            'toContainerId' => (int) $target,
            'templateId' => $payload['templateId'] ?? null,
            'instanceId' => $payload['instanceId'] ?? null,
            'quantity' => $payload['quantity'] ?? 1,
            'ownerCode' => $ownerCode,
        ]);
    }

    public function merge(int $campaignId, array $payload): array
    {
        $leftInstanceId = (int) ($payload['leftInstanceId'] ?? 0);
        $rightInstanceId = (int) ($payload['rightInstanceId'] ?? 0);
        $choices = (array) ($payload['choices'] ?? []);

        if (!$leftInstanceId || !$rightInstanceId || $leftInstanceId === $rightInstanceId) {
            return ['ok' => false, 'code' => 'invalid_payload', 'status' => 400];
        }

        $left = $this->instanceModel
            ->where('campaign_id', $campaignId)
            ->find($leftInstanceId);
        $right = $this->instanceModel
            ->where('campaign_id', $campaignId)
            ->find($rightInstanceId);

        if (!$left || !$right) {
            return ['ok' => false, 'code' => 'not_found', 'status' => 404];
        }

        $leftMeta = (array) ($left['data_override_json'] ?? []);
        $rightMeta = (array) ($right['data_override_json'] ?? []);

        $fields = ['NAME', 'ITEM_PLACE', 'PERSONAL_PSEU', 'PERSONAL_DESC', 'PERSONAL_COST', 'DESCRIPTION', 'IMG_CLASS', 'PRIZE', 'CHARGE'];
        foreach ($fields as $field) {
            if (($choices[$field] ?? 'left') === 'right') {
                $leftMeta[$field] = $rightMeta[$field] ?? ($right['name_override'] ?? null);
            }
        }

        $this->db->transStart();
        $this->instanceModel->update($leftInstanceId, [
            'name_override' => (string) ($leftMeta['NAME'] ?? ($left['name_override'] ?? '')),
            'data_override_json' => $leftMeta,
            'note' => (string) ($leftMeta['PERSONAL_DESC'] ?? ($left['note'] ?? '')),
        ]);

        $this->containerInstanceItemModel
            ->where('campaign_id', $campaignId)
            ->where('instance_id', $rightInstanceId)
            ->delete();

        $this->instanceModel->delete($rightInstanceId);
        $this->db->transComplete();

        return [
            'ok' => $this->db->transStatus() !== false,
            'leftInstanceId' => $leftInstanceId,
            'containerState' => $this->getContainers(
                $campaignId,
                strtoupper((string) ($payload['ownerCode'] ?? 'BG1'))
            ),
        ];
    }

    private function moveTemplateQuantity(
        int $campaignId,
        int $templateId,
        int $fromContainerId,
        int $toContainerId,
        int $quantity
    ): array {
        $source = $this->containerTemplateItemModel
            ->where('campaign_id', $campaignId)
            ->where('container_id', $fromContainerId)
            ->where('template_id', $templateId)
            ->first();

        if (!$source) {
            return ['ok' => false, 'code' => 'not_found', 'status' => 404];
        }

        $available = $source['quantity'];
        if ($available !== null && (int) $available < $quantity) {
            return ['ok' => false, 'code' => 'insufficient_stock', 'status' => 409];
        }

        $target = $this->containerTemplateItemModel
            ->where('campaign_id', $campaignId)
            ->where('container_id', $toContainerId)
            ->where('template_id', $templateId)
            ->first();

        if ($available !== null) {
            $next = (int) $available - $quantity;
            if ($next <= 0) {
                $this->containerTemplateItemModel->delete((int) $source['id']);
            } else {
                $this->containerTemplateItemModel->update((int) $source['id'], ['quantity' => $next]);
            }
        }

        if ($target) {
            $targetQty = $target['quantity'] === null ? null : (int) $target['quantity'];
            $updatedQty = $targetQty === null ? null : ($targetQty + $quantity);
            $this->containerTemplateItemModel->update((int) $target['id'], ['quantity' => $updatedQty]);
        } else {
            $this->containerTemplateItemModel->insert([
                'campaign_id' => $campaignId,
                'container_id' => $toContainerId,
                'template_id' => $templateId,
                'quantity' => $quantity,
                'price_override' => $source['price_override'] ?? null,
            ]);
        }

        return ['ok' => true];
    }
}
