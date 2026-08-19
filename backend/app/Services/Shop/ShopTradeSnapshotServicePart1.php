<?php

namespace App\Services\Shop;

use App\Models\ShopContainerModel;
use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerTemplateItemModel;

trait ShopTradeSnapshotServicePart1
{
    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->containerModel = new ShopContainerModel();
        $this->containerTemplateItemModel = new ShopContainerTemplateItemModel();
        $this->containerInstanceItemModel = new ShopContainerInstanceItemModel();
        $this->walletService = new ShopWalletService();
    }

    public function capture(
        int $campaignId,
        string $ownerCode,
        int $shopId,
        array $templateIds,
        ?int $shopContainerId = null,
        ?int $playerContainerId = null
    ): array {
        $containers = $this->resolveContainers($campaignId, $ownerCode, $shopId, $shopContainerId, $playerContainerId);
        $uniqueTemplateIds = array_values(array_unique(array_filter(array_map('intval', $templateIds))));

        return [
            'capturedAt' => date('Y-m-d H:i:s'),
            'campaignId' => $campaignId,
            'ownerCode' => strtoupper($ownerCode),
            'shopId' => $shopId,
            'containers' => $containers,
            'wallets' => [
                'owner' => $this->captureWallet($campaignId, strtoupper($ownerCode)),
                'shop' => null,
            ],
            'items' => $this->captureItems($campaignId, $uniqueTemplateIds, $containers),
        ];
    }

    public function apply(array $snapshot): array
    {
        $campaignId = (int) ($snapshot['campaignId'] ?? 0);
        $ownerWallet = (array) ($snapshot['wallets']['owner'] ?? []);
        $items = array_values((array) ($snapshot['items'] ?? []));

        if (!$campaignId) {
            return $this->error('invalid_snapshot', 400);
        }

        if (!empty($ownerWallet['ownerCode'])) {
            $balances = (array) ($ownerWallet['balances'] ?? []);
            if ($balances) {
                $currentBalances = $this->walletService->getBalanceMap(
                    $campaignId,
                    strtoupper((string) $ownerWallet['ownerCode'])
                );
                foreach (array_diff(array_keys($currentBalances), array_keys($balances)) as $currencyCode) {
                    $this->walletService->setBalance(
                        $campaignId,
                        strtoupper((string) $ownerWallet['ownerCode']),
                        (string) $currencyCode,
                        0
                    );
                }
                foreach ($balances as $currencyCode => $balance) {
                    $this->walletService->setBalance(
                        $campaignId,
                        strtoupper((string) $ownerWallet['ownerCode']),
                        (string) $currencyCode,
                        (int) $balance
                    );
                }
            } else {
                $this->walletService->setBalance(
                    $campaignId,
                    strtoupper((string) $ownerWallet['ownerCode']),
                    $this->walletService->primaryCurrencyCode(
                        $campaignId,
                        strtoupper((string) $ownerWallet['ownerCode'])
                    ),
                    (int) ($ownerWallet['brassBalance'] ?? 0)
                );
            }
        }

        foreach ($items as $entry) {
            $entry = (array) $entry;
            if (($entry['kind'] ?? '') === 'instance_scope') {
                $this->restoreInstanceScope($campaignId, $entry);
            } else {
                $this->restoreItemEntry($campaignId, $entry);
            }
        }

        return ['ok' => true];
    }

    public function currentDiffersFromSnapshot(array $snapshot): bool
    {
        $campaignId = (int) ($snapshot['campaignId'] ?? 0);
        if (!$campaignId) {
            return true;
        }

        $ownerWallet = (array) ($snapshot['wallets']['owner'] ?? []);
        if (!empty($ownerWallet['ownerCode'])) {
            $currentWallet = $this->captureWallet($campaignId, strtoupper((string) $ownerWallet['ownerCode']));
            $expectedBalances = (array) ($ownerWallet['balances'] ?? []);
            if ($expectedBalances) {
                if ((array) ($currentWallet['balances'] ?? []) !== $expectedBalances) {
                    return true;
                }
            } elseif ((int) ($currentWallet['brassBalance'] ?? 0) !== (int) ($ownerWallet['brassBalance'] ?? 0)) {
                return true;
            }
        }

        foreach ((array) ($snapshot['items'] ?? []) as $entry) {
            $entry = (array) $entry;
            if (($entry['kind'] ?? '') === 'instance_scope') {
                $current = $this->captureInstancePlacements(
                    $campaignId,
                    (int) ($entry['templateId'] ?? 0),
                    array_map('intval', (array) ($entry['containerIds'] ?? []))
                );
                if ($this->normalizeInstancePlacements($current) !== $this->normalizeInstancePlacements((array) ($entry['placements'] ?? []))) {
                    return true;
                }
                continue;
            }
            $containerId = (int) ($entry['containerId'] ?? 0);
            $templateId = (int) ($entry['templateId'] ?? 0);
            if (!$containerId || !$templateId) {
                return true;
            }
            $current = $this->containerTemplateItemModel
                ->where('campaign_id', $campaignId)
                ->where('container_id', $containerId)
                ->where('template_id', $templateId)
                ->first();
            $expectedExists = (bool) ($entry['exists'] ?? false);
            if (!$expectedExists && $current) {
                return true;
            }
            if ($expectedExists && !$current) {
                return true;
            }
            if ($expectedExists) {
                $expectedQuantity = $entry['quantity'];
                $currentQuantity = $current['quantity'];
                if ($expectedQuantity === null) {
                    if ($currentQuantity !== null) {
                        return true;
                    }
                } elseif ((int) $currentQuantity !== (int) $expectedQuantity) {
                    return true;
                }
                if ((string) ($current['price_override'] ?? '') !== (string) ($entry['priceOverride'] ?? '')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function resolveContainers(
        int $campaignId,
        string $ownerCode,
        int $shopId,
        ?int $shopContainerId,
        ?int $playerContainerId
    ): array {
        $shopContainer = $shopContainerId
            ? $this->containerModel->find($shopContainerId)
            : $this->containerModel
                ->where('campaign_id', $campaignId)
                ->where('container_type', 'SHOP')
                ->where('shop_id', $shopId)
                ->first();
        $playerContainer = $playerContainerId
            ? $this->containerModel->find($playerContainerId)
            : $this->containerModel
                ->where('campaign_id', $campaignId)
                ->where('container_type', 'CHARACTER')
                ->where('owner_code', strtoupper($ownerCode))
                ->first();

        return [
            'shop' => [
                'id' => $shopContainer ? (int) $shopContainer['id'] : null,
                'name' => (string) ($shopContainer['name'] ?? ''),
            ],
            'owner' => [
                'id' => $playerContainer ? (int) $playerContainer['id'] : null,
                'name' => (string) ($playerContainer['name'] ?? strtoupper($ownerCode)),
            ],
        ];
    }

    private function captureWallet(int $campaignId, string $ownerCode): array
    {
        $balances = $this->walletService->getBalanceMap($campaignId, $ownerCode);
        ksort($balances);
        $defaultCurrencyCode = $this->walletService->primaryCurrencyCode($campaignId, $ownerCode);

        return [
            'ownerCode' => $ownerCode,
            'exists' => !empty($balances),
            'defaultCurrencyCode' => $defaultCurrencyCode,
            'brassBalance' => (int) ($balances[$defaultCurrencyCode] ?? 0),
            'balances' => $balances,
        ];
    }

    private function captureItems(int $campaignId, array $templateIds, array $containers): array
    {
        $items = [];
        $containerIds = array_values(array_filter([
            (int) ($containers['shop']['id'] ?? 0),
            (int) ($containers['owner']['id'] ?? 0),
        ]));

        foreach ($templateIds as $templateId) {
            $items[] = [
                'kind' => 'instance_scope',
                'templateId' => (int) $templateId,
                'containerIds' => $containerIds,
                'placements' => $this->captureInstancePlacements(
                    $campaignId,
                    (int) $templateId,
                    $containerIds
                ),
            ];
            foreach ($containerIds as $containerId) {
                $row = $this->containerTemplateItemModel
                    ->where('campaign_id', $campaignId)
                    ->where('container_id', $containerId)
                    ->where('template_id', (int) $templateId)
                    ->first();
                $items[] = [
                    'kind' => 'template_row',
                    'containerId' => (int) $containerId,
                    'templateId' => (int) $templateId,
                    'exists' => (bool) $row,
                    'quantity' => $row ? $row['quantity'] : null,
                    'priceOverride' => $row && $row['price_override'] !== null
                        ? (int) $row['price_override']
                        : null,
                ];
            }
        }

        return $items;
    }
}
