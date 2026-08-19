<?php

namespace App\Services\Shop;

use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopItemInstanceModel;
use App\Models\ShopModel;
use App\Models\ShopTemplateModel;

trait ShopContainerServicePart1
{
    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->containerModel = new ShopContainerModel();
        $this->containerTemplateItemModel = new ShopContainerTemplateItemModel();
        $this->containerInstanceItemModel = new ShopContainerInstanceItemModel();
        $this->instanceModel = new ShopItemInstanceModel();
        $this->templateModel = new ShopTemplateModel();
        $this->shopModel = new ShopModel();
    }

    public function ensureBaseContainers(int $campaignId, string $ownerCode = 'BG1'): array
    {
        return (new ShopContainerInitializer())->ensureAndMap(
            $campaignId,
            strtoupper($ownerCode ?: 'BG1')
        );
    }

    public function getContainerMap(int $campaignId, string $ownerCode = 'BG1'): array
    {
        $ownerCode = strtoupper($ownerCode ?: 'BG1');

        $containers = $this->containerModel
            ->where('campaign_id', $campaignId)
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->findAll();

        $map = [
            'DEFAULT' => null,
            'TRASH' => null,
            'CHARACTER' => null,
            'OWNER_TRASH' => null,
            'SHOP_BY_ID' => [],
            'ALL' => $containers,
        ];

        foreach ($containers as $container) {
            if ($container['container_type'] === 'SYSTEM' && $container['system_key'] === 'DEFAULT') {
                $map['DEFAULT'] = (int) $container['id'];
            } elseif ($container['container_type'] === 'SYSTEM' && $container['system_key'] === 'TRASH') {
                $map['TRASH'] = (int) $container['id'];
            } elseif ($container['container_type'] === 'CHARACTER' && strtoupper((string) $container['owner_code']) === $ownerCode) {
                $map['CHARACTER'] = (int) $container['id'];
            } elseif ($container['container_type'] === 'TRASH' && strtoupper((string) $container['owner_code']) === $ownerCode) {
                $map['OWNER_TRASH'] = (int) $container['id'];
            } elseif ($container['container_type'] === 'SHOP' && $container['shop_id']) {
                $map['SHOP_BY_ID'][(int) $container['shop_id']] = (int) $container['id'];
            }
        }

        return $map;
    }

    public function getContainers(int $campaignId, string $ownerCode = 'BG1'): array
    {
        $this->ensureBaseContainers($campaignId, $ownerCode);
        $containers = $this->containerModel
            ->where('campaign_id', $campaignId)
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->findAll();

        $templateRows = $this->containerTemplateItemModel
            ->where('campaign_id', $campaignId)
            ->findAll();

        $instanceRows = $this->containerInstanceItemModel
            ->where('campaign_id', $campaignId)
            ->findAll();

        return [
            'containers' => $containers,
            'templateRows' => $templateRows,
            'instanceRows' => $instanceRows,
            'itemInstances' => $this->instanceModel
                ->where('campaign_id', $campaignId)
                ->orderBy('id', 'ASC')
                ->findAll(),
        ];
    }

    public function getContainersForOwner(int $campaignId, string $ownerCode): array
    {
        $data = $this->getContainers($campaignId, $ownerCode);
        $ownerCode = strtoupper($ownerCode);
        $data['containers'] = array_values(array_filter($data['containers'], static function (array $container) use ($ownerCode): bool {
            $type = strtoupper((string) ($container['container_type'] ?? ''));
            return $type === 'SHOP' || (
                in_array($type, ['CHARACTER', 'TRASH'], true)
                && strtoupper((string) ($container['owner_code'] ?? '')) === $ownerCode
            );
        }));
        $containerIds = array_map(static fn (array $container): int => (int) $container['id'], $data['containers']);
        $data['templateRows'] = array_values(array_filter($data['templateRows'], static fn (array $row): bool => in_array((int) $row['container_id'], $containerIds, true)));
        $data['instanceRows'] = array_values(array_filter($data['instanceRows'], static fn (array $row): bool => in_array((int) $row['container_id'], $containerIds, true)));
        $instanceIds = array_map(static fn (array $row): int => (int) $row['instance_id'], $data['instanceRows']);
        $data['itemInstances'] = array_values(array_filter($data['itemInstances'], static fn (array $row): bool => in_array((int) $row['id'], $instanceIds, true)));
        return $data;
    }

    public function clearInstancePlacements(int $campaignId, int $containerId): void
    {
        $this->containerInstanceItemModel
            ->where('campaign_id', $campaignId)
            ->where('container_id', $containerId)
            ->delete();
    }

    public function move(int $campaignId, array $payload): array
    {
        return $this->moveMany($campaignId, [$payload]);
    }

    public function moveMany(int $campaignId, array $moves): array
    {
        if (!$moves) {
            return ['ok' => false, 'code' => 'invalid_payload', 'status' => 400];
        }

        $this->db->transBegin();
        $changed = [];
        foreach (array_values($moves) as $index => $move) {
            if (!is_array($move)) {
                $this->db->transRollback();
                return ['ok' => false, 'code' => 'invalid_move', 'status' => 400, 'moveIndex' => $index];
            }
            $result = $this->moveWithinTransaction($campaignId, $move);
            if (!($result['ok'] ?? false)) {
                $this->db->transRollback();
                $result['moveIndex'] = $index;
                return $result;
            }
            $changed[] = $result['changed'];
        }

        if (!$this->db->transStatus()) {
            $this->db->transRollback();
            return ['ok' => false, 'code' => 'transaction_failed', 'status' => 500];
        }
        $this->db->transCommit();

        return [
            'ok' => true,
            'changed' => $changed,
            'containerState' => $this->getContainers($campaignId, (string) ($moves[0]['ownerCode'] ?? 'BG1')),
        ];
    }

    public function setTemplateQuantities(int $campaignId, array $changes, string $ownerCode = 'BG1'): array
    {
        if (!$changes) {
            return ['ok' => false, 'code' => 'invalid_payload', 'status' => 400];
        }

        $this->db->transBegin();
        $changed = [];
        foreach (array_values($changes) as $index => $change) {
            if (!is_array($change)) {
                $this->db->transRollback();
                return ['ok' => false, 'code' => 'invalid_change', 'status' => 400, 'changeIndex' => $index];
            }
            $containerId = (int) ($change['containerId'] ?? 0);
            $templateId = (int) ($change['templateId'] ?? 0);
            $quantity = filter_var($change['quantity'] ?? null, FILTER_VALIDATE_INT);
            if (
                !$containerId
                || !$templateId
                || $quantity === false
                || $quantity < 0
                || !$this->containersBelongToCampaign($campaignId, [$containerId])
            ) {
                $this->db->transRollback();
                return ['ok' => false, 'code' => 'invalid_quantity_change', 'status' => 400, 'changeIndex' => $index];
            }

            $row = $this->containerTemplateItemModel
                ->where('campaign_id', $campaignId)
                ->where('container_id', $containerId)
                ->where('template_id', $templateId)
                ->first();
            if (!$row) {
                $this->db->transRollback();
                return ['ok' => false, 'code' => 'not_found', 'status' => 404, 'changeIndex' => $index];
            }

            if ($quantity === 0) {
                $this->containerTemplateItemModel->delete((int) $row['id']);
            } else {
                $this->containerTemplateItemModel->update((int) $row['id'], ['quantity' => $quantity]);
            }
            $changed[] = [
                'containerId' => $containerId,
                'templateId' => $templateId,
                'quantity' => $quantity,
            ];
        }

        if (!$this->db->transStatus()) {
            $this->db->transRollback();
            return ['ok' => false, 'code' => 'transaction_failed', 'status' => 500];
        }
        $this->db->transCommit();

        return [
            'ok' => true,
            'changed' => $changed,
            'containerState' => $this->getContainers($campaignId, strtoupper($ownerCode ?: 'BG1')),
        ];
    }
}
