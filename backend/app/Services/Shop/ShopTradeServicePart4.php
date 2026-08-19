<?php

namespace App\Services\Shop;

use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopTradeTransactionModel;
use App\Models\ShopModel;

trait ShopTradeServicePart4
{
    private function buyLegacy(int $campaignId, array $payload, array $authContext, ?string $idempotencyKey = null): array
    {
        $ownerCode = strtoupper((string) ($payload['ownerCode'] ?? ''));
        $shopId = (int) ($payload['shopId'] ?? 0);
        $selections = array_values((array) ($payload['selections'] ?? []));

        if (!$ownerCode || !$shopId || !$selections) {
            $error = $this->error('invalid_payload', 400);
            $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode ?: null, $shopId ?: null, $payload, $error, 'FAILED', 'invalid_payload', 0);
            return $error;
        }

        $cached = $this->resolveIdempotent($campaignId, 'BUY', $idempotencyKey, $ownerCode);
        if ($cached !== null) {
            $cached['idempotentReplay'] = true;
            return $cached;
        }

        $shop = $this->shopModel
            ->where('campaign_id', $campaignId)
            ->where('id', $shopId)
            ->where('deleted_at', null)
            ->first();

        if (!$shop) {
            $error = $this->error('not_found', 404);
            $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'not_found', 0);
            return $error;
        }
        if ((int) ($shop['is_active'] ?? 0) !== 1) {
            $error = $this->error('shop_inactive', 409);
            $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'shop_inactive', 0);
            return $error;
        }

        $profile = $this->profileService->getProfile($campaignId, $shopId) ?: [];

        $containers = $this->containerService->ensureBaseContainers($campaignId, $ownerCode);
        $shopContainerId = (int) ($containers['SHOP_BY_ID'][$shopId] ?? 0);
        $playerContainerId = (int) ($containers['CHARACTER'] ?? 0);
        if (!$shopContainerId || !$playerContainerId) {
            $error = $this->error('not_found', 404);
            $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'not_found', 0);
            return $error;
        }

        $items = [];
        $total = 0;

        foreach ($selections as $selection) {
            $templateId = (int) ($selection['templateId'] ?? 0);
            $quantity = (int) ($selection['quantity'] ?? 0);
            if (!$templateId) {
                $error = $this->error('invalid_payload', 400);
                $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'invalid_payload', 0);
                return $error;
            }
            if ($quantity <= 0) {
                $error = $this->error('invalid_quantity', 400);
                $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'invalid_quantity', 0);
                return $error;
            }

            $source = $this->containerTemplateItemModel
                ->where('campaign_id', $campaignId)
                ->where('container_id', $shopContainerId)
                ->where('template_id', $templateId)
                ->first();

            if (!$source) {
                $error = $this->error('not_found', 404);
                $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'not_found', 0);
                return $error;
            }

            if ($source['quantity'] !== null && (int) $source['quantity'] < $quantity) {
                $error = $this->error('insufficient_stock', 409);
                $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'insufficient_stock', 0);
                return $error;
            }

            $template = $this->templateModel
                ->where('campaign_id', $campaignId)
                ->where('deleted_at', null)
                ->find($templateId);
            if (!$template) {
                $error = $this->error('not_found', 404);
                $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'not_found', 0);
                return $error;
            }

            $pricing = $this->pricingService->calculateForTrade(
                $template,
                $profile,
                [
                    'QUANTITY' => $source['quantity'],
                    'PRICE_OVERRIDE' => $source['price_override'],
                    'quantityRequested' => $quantity,
                    'actorCode' => $ownerCode,
                    'condition' => $this->trustedCondition($selection, $authContext),
                ],
                'buy'
            );
            $canForcePrices = $this->canForcePrices($payload, $authContext);
            if (empty($pricing['exchangeRateConfigured']) && !$canForcePrices) {
                $error = $this->error('missing_exchange_rate', 409);
                $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'missing_exchange_rate', 0);
                return $error;
            }
            $forcedPrice = isset($selection['finalPrice']) ? (int) $selection['finalPrice'] : null;
            $unitPrice = $forcedPrice !== null && $canForcePrices
                ? max(0, $forcedPrice)
                : (int) ($pricing['finalPrice'] ?? 0);
            if ($forcedPrice !== null && $canForcePrices) {
                $pricing['gmForcedFinalPrice'] = $unitPrice;
            }
            $items[] = [
                'template_id' => $templateId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'currency' => (string) ($pricing['settlementCurrencyCode'] ?? $template['currency_code'] ?? 'generic'),
                'pricing' => $pricing,
                'source_row' => $source,
                'charge' => max(0, (int) ($template['charge'] ?? 0)) * $quantity,
            ];
            $total += $unitPrice * $quantity;
        }

        $addedCharge = array_sum(array_map(static fn (array $item): int => (int) ($item['charge'] ?? 0), $items));
        if ($this->currentContainerCharge($campaignId, $playerContainerId) + $addedCharge > $this->characterCarryLimit()) {
            $error = $this->error('encumbrance_exceeded', 409);
            $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'encumbrance_exceeded', 0);
            return $error;
        }

        $currencyCode = strtolower((string) ($items[0]['currency'] ?? 'generic'));
        $wallet = $this->walletService->getOrCreateBalance($campaignId, $ownerCode, $currencyCode);
        if ((int) $wallet['balance'] < $total) {
            $error = $this->error('insufficient_funds', 409);
            $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'insufficient_funds', $total);
            return $error;
        }

        $templateIds = array_map(static function (array $item): int {
            return (int) $item['template_id'];
        }, $items);
        $beforeSnapshot = $this->snapshotService->capture(
            $campaignId,
            $ownerCode,
            $shopId,
            $templateIds,
            $shopContainerId,
            $playerContainerId
        );

        $this->db->transBegin();

        $walletDebited = $this->walletService->debit($wallet, $total);

        if (!$walletDebited) {
            $this->db->transRollback();
            $error = $this->error('insufficient_funds', 409);
            $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'insufficient_funds', $total);
            return $error;
        }

        foreach ($items as $item) {
            $source = $item['source_row'];
            $sourceId = (int) $source['id'];
            $qty = (int) $item['quantity'];

            if ($source['quantity'] !== null) {
                $updated = $this->db->query(
                    'UPDATE shop_container_template_items SET quantity = quantity - ? WHERE id = ? AND quantity >= ?',
                    [$qty, $sourceId, $qty]
                );
                if (!$updated || $this->db->affectedRows() !== 1) {
                    $this->db->transRollback();
                    $error = $this->error('insufficient_stock', 409);
                    $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'insufficient_stock', $total);
                    return $error;
                }

                $this->db->query('DELETE FROM shop_container_template_items WHERE id = ? AND quantity <= 0', [$sourceId]);
            }

            $added = $this->upsertTemplateQuantity(
                $campaignId,
                $playerContainerId,
                (int) $item['template_id'],
                $qty,
                null
            );

            if (!$added) {
                $this->db->transRollback();
                $error = $this->error('transaction_failed', 500);
                $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'transaction_failed', $total);
                return $error;
            }
        }
        $nextBalance = max(0, ((int) $wallet['balance']) - $total);
        $this->walletService->syncLegacyDefaultBalance($campaignId, $ownerCode, $currencyCode, $nextBalance);

        $response = [
            'ok' => true,
            'ownerCode' => $ownerCode,
            'shopId' => $shopId,
            'totalBrass' => $total,
            'walletBrass' => $nextBalance,
            'walletBalance' => $nextBalance,
            'walletCurrencyCode' => $currencyCode,
            'walletBalances' => $this->walletService->getBalances($campaignId, $ownerCode),
            'currency' => $currencyCode,
            'items' => array_map(function (array $item): array {
                return [
                    'templateId' => (int) $item['template_id'],
                    'quantity' => (int) $item['quantity'],
                    'unitPrice' => (int) $item['unit_price'],
                    'currency' => (string) ($item['currency'] ?? 'generic'),
                    'pricing' => (array) ($item['pricing'] ?? []),
                ];
            }, $items),
        ];

        if (!$this->db->transStatus()) {
            $this->db->transRollback();
            $error = $this->error('transaction_failed', 500);
            $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'transaction_failed', $total);
            return $error;
        }

        $afterSnapshot = $this->snapshotService->capture(
            $campaignId,
            $ownerCode,
            $shopId,
            $templateIds,
            $shopContainerId,
            $playerContainerId
        );
        $response['containerState'] = $this->containerStateForAuth($campaignId, $ownerCode, $authContext);
        try {
            $this->logTransaction($campaignId, 'BUY', $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $response, 'executed', null, $total, [
                'shop' => $shop,
                'items' => $items,
                'beforeSnapshot' => $beforeSnapshot,
                'afterSnapshot' => $afterSnapshot,
            ]);
        } catch (\Throwable $error) {
            $this->db->transRollback();
            return $this->error('transaction_failed', 500);
        }

        if (!$this->db->transStatus()) {
            $this->db->transRollback();
            return $this->error('transaction_failed', 500);
        }
        $this->db->transCommit();

        return $response;
    }
}
