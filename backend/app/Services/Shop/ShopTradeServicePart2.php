<?php

namespace App\Services\Shop;

use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopTradeTransactionModel;
use App\Models\ShopModel;

trait ShopTradeServicePart2
{
    private function tradeInstances(
        int $campaignId,
        array $payload,
        array $authContext,
        ?string $idempotencyKey,
        string $transactionType
    ): array {
        $ownerCode = strtoupper((string) ($payload['ownerCode'] ?? ''));
        $shopId = (int) ($payload['shopId'] ?? 0);
        $selections = array_values((array) ($payload['selections'] ?? []));
        $isQuote = $transactionType === 'QUOTE_BUY';
        $isBuy = $transactionType === 'BUY' || $isQuote;

        if (!$ownerCode || !$shopId || !$selections) {
            $error = $this->error('invalid_payload', 400);
            if (!$isQuote) $this->logTransaction($campaignId, $transactionType, $idempotencyKey, $authContext, $ownerCode ?: null, $shopId ?: null, $payload, $error, 'FAILED', 'invalid_payload', 0);
            return $error;
        }

        $cached = $isQuote ? null : $this->resolveIdempotent($campaignId, $transactionType, $idempotencyKey, $ownerCode);
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
            if (!$isQuote) $this->logTransaction($campaignId, $transactionType, $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'not_found', 0);
            return $error;
        }
        if ((int) ($shop['is_active'] ?? 0) !== 1) {
            $error = $this->error('shop_inactive', 409);
            if (!$isQuote) $this->logTransaction($campaignId, $transactionType, $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'shop_inactive', 0);
            return $error;
        }

        $profile = $this->profileService->getProfile($campaignId, $shopId) ?: [];
        $containers = $this->containerService->ensureBaseContainers($campaignId, $ownerCode);
        $shopContainerId = (int) ($containers['SHOP_BY_ID'][$shopId] ?? 0);
        $playerContainerId = (int) ($containers['CHARACTER'] ?? 0);
        if (!$shopContainerId || !$playerContainerId) {
            $error = $this->error('not_found', 404);
            if (!$isQuote) $this->logTransaction($campaignId, $transactionType, $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'not_found', 0);
            return $error;
        }

        $sourceContainerId = $isBuy ? $shopContainerId : $playerContainerId;
        $targetContainerId = $isBuy ? $playerContainerId : $shopContainerId;
        $reservedInstanceIds = [];
        $items = [];
        $total = 0;

        foreach ($selections as $selection) {
            $templateId = (int) ($selection['templateId'] ?? 0);
            $instanceId = (int) ($selection['instanceId'] ?? $selection['clientId'] ?? 0);
            $quantity = max(0, (int) ($selection['quantity'] ?? 0));
            if (!$templateId || $quantity <= 0) {
                $code = !$templateId ? 'invalid_payload' : 'invalid_quantity';
                $error = $this->error($code, 400);
                if (!$isQuote) $this->logTransaction($campaignId, $transactionType, $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', $code, 0);
                return $error;
            }

            if ($instanceId > 0) {
                $quantity = 1;
            }
            $placements = $this->findInstancePlacements(
                $campaignId,
                $sourceContainerId,
                $templateId,
                $quantity,
                $instanceId,
                $reservedInstanceIds
            );
            $templateQuantity = 0;
            $sourceTemplateRow = null;
            $remainingQuantity = $quantity - count($placements);
            if ($remainingQuantity > 0 && $instanceId <= 0) {
                $sourceTemplateRow = $this->containerTemplateItemModel
                    ->where('campaign_id', $campaignId)
                    ->where('container_id', $sourceContainerId)
                    ->where('template_id', $templateId)
                    ->first();
                $availableTemplateQuantity = $sourceTemplateRow && $sourceTemplateRow['quantity'] === null
                    ? $remainingQuantity
                    : (int) ($sourceTemplateRow['quantity'] ?? 0);
                if ($availableTemplateQuantity >= $remainingQuantity) {
                    $templateQuantity = $remainingQuantity;
                }
            }
            if (count($placements) + $templateQuantity < $quantity) {
                $error = $this->error('insufficient_stock', 409);
                if (!$isQuote) $this->logTransaction($campaignId, $transactionType, $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'insufficient_stock', 0);
                return $error;
            }
            foreach ($placements as $placement) {
                $reservedInstanceIds[] = (int) $placement['instance_id'];
            }

            $template = $this->templateModel
                ->where('campaign_id', $campaignId)
                ->where('deleted_at', null)
                ->find($templateId);
            if (!$template) {
                $error = $this->error('not_found', 404);
                if (!$isQuote) $this->logTransaction($campaignId, $transactionType, $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'not_found', 0);
                return $error;
            }

            $shopTemplateRow = $this->containerTemplateItemModel
                ->where('campaign_id', $campaignId)
                ->where('container_id', $shopContainerId)
                ->where('template_id', $templateId)
                ->first();
            $instanceAvailability = $this->countTemplateInstances($campaignId, $shopContainerId, $templateId);
            $availability = $shopTemplateRow && $shopTemplateRow['quantity'] === null
                ? null
                : $instanceAvailability + (int) ($shopTemplateRow['quantity'] ?? 0);
            $priceOverride = $isBuy
                ? ($placements[0]['price_override'] ?? $sourceTemplateRow['price_override'] ?? null)
                : null;
            $pricing = $this->pricingService->calculateForTrade(
                $template,
                $profile,
                [
                    'QUANTITY' => $availability,
                    'PRICE_OVERRIDE' => $priceOverride,
                    'quantityRequested' => $quantity,
                    'actorCode' => $ownerCode,
                    'condition' => $this->trustedCondition($selection, $authContext, $placements),
                ],
                $isBuy ? 'buy' : 'sell'
            );
            $canForcePrices = $this->canForcePrices($payload, $authContext);
            if (empty($pricing['exchangeRateConfigured']) && !$canForcePrices) {
                $error = $this->error('missing_exchange_rate', 409);
                if (!$isQuote) $this->logTransaction($campaignId, $transactionType, $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'missing_exchange_rate', 0);
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
                'instance_ids' => array_map(static function (array $placement): int {
                    return (int) $placement['instance_id'];
                }, $placements),
                'placement_ids' => array_map(static function (array $placement): int {
                    return (int) $placement['placement_id'];
                }, $placements),
                'quantity' => $quantity,
                'template_quantity' => $templateQuantity,
                'source_template_row' => $sourceTemplateRow,
                'unit_price' => $unitPrice,
                'currency' => (string) ($pricing['settlementCurrencyCode'] ?? $template['currency_code'] ?? 'generic'),
                'pricing' => $pricing,
                'template' => $template,
                'charge' => array_sum(array_map(function (array $placement) use ($template): int {
                    $meta = $placement['data_override_json'] ?? [];
                    if (is_string($meta)) {
                        $meta = json_decode($meta, true) ?: [];
                    }
                    return max(0, (int) ($meta['CHARGE'] ?? $template['charge'] ?? 0));
                }, $placements)) + max(0, (int) ($template['charge'] ?? 0)) * $templateQuantity,
            ];
            $total += $unitPrice * $quantity;
        }

        if ($isBuy) {
            $addedCharge = array_sum(array_map(static fn (array $item): int => (int) ($item['charge'] ?? 0), $items));
            if ($this->currentContainerCharge($campaignId, $playerContainerId) + $addedCharge > $this->characterCarryLimit()) {
                $error = $this->error('encumbrance_exceeded', 409);
                if (!$isQuote) $this->logTransaction($campaignId, $transactionType, $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'encumbrance_exceeded', 0);
                return $error;
            }
        }

        $currencyCode = strtolower((string) ($items[0]['currency'] ?? 'generic'));
        $currencyPolicy = (array) ($profile['pricingConfig']['currencyPolicy'] ?? []);
        $selectedCurrencyCodes = null;
        if (array_key_exists('selectedCurrencyCodes', $payload)) {
            $selectedCurrencyCodes = array_values((array) $payload['selectedCurrencyCodes']);
        } elseif (array_key_exists('selectedCurrencyCodes', (array) ($payload['payment'] ?? []))) {
            $selectedCurrencyCodes = array_values((array) $payload['payment']['selectedCurrencyCodes']);
        }
        $itemContext = array_map(static fn (array $item): array => [
            'templateId' => (int) $item['template_id'],
            'instanceIds' => array_values($item['instance_ids']),
            'quantity' => (int) $item['quantity'],
            'unitPrice' => (int) $item['unit_price'],
        ], $items);
        $contextFingerprint = hash('sha256', json_encode([
            'items' => $itemContext,
            'currencyPolicy' => $currencyPolicy,
        ], JSON_UNESCAPED_SLASHES));
        $paymentQuote = $this->paymentService->quote(
            $shopId,
            $ownerCode,
            $total,
            $currencyCode,
            $currencyPolicy,
            $this->walletService->getBalances($campaignId, $ownerCode),
            $selectedCurrencyCodes,
            $contextFingerprint
        );
        if ($isQuote) {
            return [
                'ok' => true,
                'paymentQuote' => $paymentQuote,
                'items' => $itemContext,
            ];
        }
        if ($isBuy && !$paymentQuote['canPay']) {
            $error = array_merge($this->error('insufficient_funds', 409), ['paymentQuote' => $paymentQuote]);
            $this->logTransaction($campaignId, $transactionType, $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'insufficient_funds', $total);
            return $error;
        }
        $providedQuoteFingerprint = (string) ($payload['payment']['quoteFingerprint'] ?? '');
        if ($isBuy && $paymentQuote['requiresConversion'] && $providedQuoteFingerprint === '') {
            return array_merge($this->error('payment_conversion_required', 409), ['paymentQuote' => $paymentQuote]);
        }
        if ($isBuy && $providedQuoteFingerprint !== '' && !hash_equals($paymentQuote['quoteFingerprint'], $providedQuoteFingerprint)) {
            return array_merge($this->error('payment_quote_stale', 409), ['paymentQuote' => $paymentQuote]);
        }

        $wallet = $this->walletService->getOrCreateBalance($campaignId, $ownerCode, $currencyCode);

        $templateIds = array_values(array_unique(array_map(static function (array $item): int {
            return (int) $item['template_id'];
        }, $items)));
        $this->db->transBegin();
        $allWalletCodes = array_map(
            static fn (array $balance): string => (string) $balance['currencyCode'],
            $this->walletService->getBalances($campaignId, $ownerCode)
        );
        $allWalletCodes[] = $currencyCode;
        $lockedWallets = $this->walletService->lockBalances($campaignId, $ownerCode, $allWalletCodes);
        $wallet = $lockedWallets[$currencyCode] ?? $wallet;
        if ($isBuy) {
            $lockedQuote = $this->paymentService->quote(
                $shopId,
                $ownerCode,
                $total,
                $currencyCode,
                $currencyPolicy,
                $lockedWallets,
                $selectedCurrencyCodes,
                $contextFingerprint
            );
            if (!$lockedQuote['canPay']) {
                $this->db->transRollback();
                return array_merge($this->error('insufficient_funds', 409), ['paymentQuote' => $lockedQuote]);
            }
            if ($lockedQuote['requiresConversion'] && $providedQuoteFingerprint === '') {
                $this->db->transRollback();
                return array_merge($this->error('payment_conversion_required', 409), ['paymentQuote' => $lockedQuote]);
            }
            if (
                $providedQuoteFingerprint !== ''
                && !hash_equals($lockedQuote['quoteFingerprint'], $providedQuoteFingerprint)
            ) {
                $this->db->transRollback();
                return array_merge($this->error('payment_quote_stale', 409), ['paymentQuote' => $lockedQuote]);
            }
            $paymentQuote = $lockedQuote;
        }
        $beforeSnapshot = $this->snapshotService->capture(
            $campaignId,
            $ownerCode,
            $shopId,
            $templateIds,
            $shopContainerId,
            $playerContainerId
        );
        $beforeSnapshot['paymentBreakdown'] = $isBuy ? $paymentQuote : null;
        foreach ($items as $item) {
            if ($item['placement_ids']) {
                $moved = $this->db->table('shop_container_instance_items')
                    ->where('campaign_id', $campaignId)
                    ->where('container_id', $sourceContainerId)
                    ->whereIn('id', $item['placement_ids'])
                    ->update([
                        'container_id' => $targetContainerId,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                if (!$moved || $this->db->affectedRows() !== count($item['placement_ids'])) {
                    $this->db->transRollback();
                    $error = $this->error('insufficient_stock', 409);
                    $this->logTransaction($campaignId, $transactionType, $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', 'insufficient_stock', $total);
                    return $error;
                }
            }
            if ((int) $item['template_quantity'] > 0) {
                $sourceRow = (array) $item['source_template_row'];
                if ($sourceRow['quantity'] !== null) {
                    $updated = $this->db->query(
                        'UPDATE shop_container_template_items SET quantity = quantity - ? WHERE id = ? AND quantity >= ?',
                        [(int) $item['template_quantity'], (int) $sourceRow['id'], (int) $item['template_quantity']]
                    );
                    if (!$updated || $this->db->affectedRows() !== 1) {
                        $this->db->transRollback();
                        return $this->error('insufficient_stock', 409);
                    }
                    $this->db->query('DELETE FROM shop_container_template_items WHERE id = ? AND quantity <= 0', [(int) $sourceRow['id']]);
                }
                if (!$this->upsertTemplateQuantity(
                    $campaignId,
                    $targetContainerId,
                    (int) $item['template_id'],
                    (int) $item['template_quantity'],
                    null
                )) {
                    $this->db->transRollback();
                    return $this->error('transaction_failed', 500);
                }
            }
        }

        $walletUpdated = $isBuy
            ? $this->walletService->debitBreakdown($lockedWallets, $paymentQuote['debits'])
            : $this->walletService->credit($wallet, $total);
        if (!$walletUpdated || !$this->db->transStatus()) {
            $this->db->transRollback();
            $code = $isBuy ? 'insufficient_funds' : 'transaction_failed';
            $error = $this->error($code, $isBuy ? 409 : 500);
            $this->logTransaction($campaignId, $transactionType, $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $error, 'FAILED', $code, $total);
            return $error;
        }

        $settlementDebit = $isBuy ? (int) ($paymentQuote['settlementDebit'] ?? 0) : 0;
        $nextBalance = $isBuy
            ? max(0, (int) $wallet['balance'] - $settlementDebit)
            : (int) $wallet['balance'] + $total;
        $this->walletService->syncLegacyDefaultBalance(
            $campaignId,
            $ownerCode,
            $currencyCode,
            $nextBalance
        );
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
            'paymentBreakdown' => $isBuy ? $paymentQuote : [
                'settlementCurrencyCode' => $currencyCode,
                'credit' => $total,
                'debits' => [],
                'requiresConversion' => false,
            ],
            'items' => array_map(static function (array $item): array {
                return [
                    'templateId' => (int) $item['template_id'],
                    'instanceId' => (int) ($item['instance_ids'][0] ?? 0),
                    'instanceIds' => array_values($item['instance_ids']),
                    'quantity' => (int) $item['quantity'],
                    'unitPrice' => (int) $item['unit_price'],
                    'currency' => (string) $item['currency'],
                    'pricing' => (array) $item['pricing'],
                ];
            }, $items),
        ];

        $afterSnapshot = $this->snapshotService->capture(
            $campaignId,
            $ownerCode,
            $shopId,
            $templateIds,
            $shopContainerId,
            $playerContainerId
        );
        $afterSnapshot['paymentBreakdown'] = $response['paymentBreakdown'];
        $response['containerState'] = $this->containerStateForAuth($campaignId, $ownerCode, $authContext);
        try {
            $this->logTransaction($campaignId, $transactionType, $idempotencyKey, $authContext, $ownerCode, $shopId, $payload, $response, 'executed', null, $total, [
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
