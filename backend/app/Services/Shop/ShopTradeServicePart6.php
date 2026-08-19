<?php

namespace App\Services\Shop;

use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopTradeTransactionModel;
use App\Models\ShopModel;

trait ShopTradeServicePart6
{
    private function resolveIdempotent(int $campaignId, string $transactionType, ?string $idempotencyKey, string $ownerCode): ?array
    {
        if (!$idempotencyKey) {
            return null;
        }

        $existing = $this->transactionModel
            ->where('campaign_id', $campaignId)
            ->where('transaction_type', $transactionType)
            ->where('idempotency_key', $this->scopedIdempotencyKey($idempotencyKey, $ownerCode))
            ->groupStart()
                ->where('status', 'executed')
                ->orWhere('status', 'SUCCESS')
            ->groupEnd()
            ->orderBy('id', 'DESC')
            ->first();

        if (!$existing) {
            return null;
        }

        if (!is_array($existing['response_json'] ?? null)) {
            return null;
        }

        return $existing['response_json'];
    }

    private function logTransaction(
        int $campaignId,
        string $transactionType,
        ?string $idempotencyKey,
        array $authContext,
        ?string $ownerCode,
        ?int $shopId,
        array $payload,
        array $response,
        string $status,
        ?string $errorCode,
        int $totalBrass,
        array $meta = []
    ): void {
        $normalizedStatus = strtolower($status) === 'success' ? 'executed' : strtolower($status);
        if ($normalizedStatus !== 'executed') {
            $idempotencyKey = null;
        } elseif ($idempotencyKey) {
            $idempotencyKey = $this->scopedIdempotencyKey($idempotencyKey, (string) $ownerCode);
        }
        $uuid = bin2hex(random_bytes(16));
        $items = array_values((array) ($meta['items'] ?? []));
        $firstItem = $items[0] ?? [];
        $itemNames = [];
        $quantity = 0;
        $basePrice = 0;
        $finalPrice = 0;
        $priceModifiers = [];
        $conditionItems = [];
        $currencies = [];

        foreach ($items as $item) {
            $template = (array) ($item['template'] ?? []);
            if (!$template) {
                $template = $this->templateModel
                    ->where('campaign_id', $campaignId)
                    ->find((int) ($item['template_id'] ?? 0));
            }
            if ($template) {
                $itemNames[] = (string) ($template['name'] ?? ('#' . (int) ($item['template_id'] ?? 0)));
                $basePrice += (int) ($template['prize'] ?? 0) * (int) ($item['quantity'] ?? 1);
            } else {
                $itemNames[] = '#' . (int) ($item['template_id'] ?? 0);
            }
            $currencies[] = (string) ($item['currency'] ?? ($template['currency_code'] ?? 'generic'));
            $quantity += (int) ($item['quantity'] ?? 0);
            $finalPrice += (int) ($item['unit_price'] ?? 0) * (int) ($item['quantity'] ?? 1);
            $priceModifiers[] = (array) ($item['pricing'] ?? []);
            $conditionItems[] = [
                'templateId' => (int) ($item['template_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
                'unitPrice' => (int) ($item['unit_price'] ?? 0),
                'basePrice' => $template ? (int) ($template['prize'] ?? 0) : 0,
                'currency' => (string) ($item['currency'] ?? ($template['currency_code'] ?? 'generic')),
                'pricing' => (array) ($item['pricing'] ?? []),
            ];
        }

        $status = strtolower($status) === 'success' ? 'executed' : strtolower($status);
        $status = $status === 'failed' || $status === 'executed' ? $status : ($status === 'FAILED' ? 'failed' : $status);
        $shop = (array) ($meta['shop'] ?? []);
        $shopName = (string) ($shop['name'] ?? '');
        $actorName = $this->ownerDisplayName($ownerCode, $payload);
        $isBuy = strtoupper($transactionType) === 'BUY';
        $history = [[
            'action' => $status === 'executed' ? 'executed' : 'failed',
            'performedBy' => isset($authContext['user_id']) ? 'user:' . (int) $authContext['user_id'] : $actorName,
            'createdAt' => date('Y-m-d H:i:s'),
            'reason' => (string) ($payload['correctionReason'] ?? ''),
            'gmNote' => (string) ($payload['gmNote'] ?? ''),
        ]];
        $currencies = array_values(array_unique(array_filter($currencies)));
        $transactionCurrency = count($currencies) > 1
            ? 'mixed'
            : (string) ($currencies[0] ?? $payload['currencyCode'] ?? 'generic');

        $this->transactionModel->insert([
            'campaign_id' => $campaignId,
            'transaction_uuid' => $uuid,
            'idempotency_key' => $idempotencyKey,
            'actor_user_id' => isset($authContext['user_id']) ? (int) $authContext['user_id'] : null,
            'actor_id' => $ownerCode,
            'actor_name' => $actorName,
            'owner_code' => $ownerCode,
            'shop_id' => $shopId,
            'shop_name' => $shopName,
            'seller_id' => $isBuy ? ('SHOP:' . (int) $shopId) : $ownerCode,
            'seller_name' => $isBuy ? $shopName : $actorName,
            'buyer_id' => $isBuy ? $ownerCode : ('SHOP:' . (int) $shopId),
            'buyer_name' => $isBuy ? $actorName : $shopName,
            'transaction_type' => $transactionType,
            'status' => $status,
            'error_code' => $errorCode,
            'item_id' => isset($firstItem['template_id']) ? 'template:' . (int) $firstItem['template_id'] : null,
            'item_template_id' => isset($firstItem['template_id']) ? (int) $firstItem['template_id'] : null,
            'item_name' => implode(', ', array_slice(array_unique($itemNames), 0, 4)),
            'quantity' => $quantity,
            'base_price' => $quantity > 0 ? (int) round($basePrice / max(1, $quantity)) : 0,
            'final_price' => $quantity > 0 ? (int) round($finalPrice / max(1, $quantity)) : $totalBrass,
            'currency' => $transactionCurrency,
            'price_modifiers_json' => $priceModifiers,
            'conditions_snapshot_json' => [
                'ownerCode' => $ownerCode,
                'shopId' => $shopId,
                'transactionType' => $transactionType,
                'items' => $conditionItems,
                'payload' => $payload,
            ],
            'before_snapshot_json' => (array) ($meta['beforeSnapshot'] ?? []),
            'after_snapshot_json' => (array) ($meta['afterSnapshot'] ?? []),
            'parent_transaction_id' => isset($payload['parentTransactionId']) ? (int) $payload['parentTransactionId'] : null,
            'correction_reason' => (string) ($payload['correctionReason'] ?? ''),
            'gm_note' => (string) ($payload['gmNote'] ?? ''),
            'performed_by' => isset($authContext['user_id']) ? 'user:' . (int) $authContext['user_id'] : null,
            'redone_by' => isset($payload['parentTransactionId'])
                ? (isset($authContext['user_id']) ? 'user:' . (int) $authContext['user_id'] : 'GM')
                : null,
            'history_json' => $history,
            'total_brass' => $totalBrass,
            'payload_json' => $payload,
            'response_json' => $response,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        log_message('info', 'shop_transaction campaign={campaign} type={type} status={status} owner={owner} shop={shop}', [
            'campaign' => $campaignId,
            'type' => $transactionType,
            'status' => $status,
            'owner' => (string) $ownerCode,
            'shop' => (string) $shopId,
        ]);
    }

    private function scopedIdempotencyKey(string $idempotencyKey, string $ownerCode): string
    {
        return substr(hash('sha256', strtoupper($ownerCode)), 0, 16)
            . ':'
            . substr($idempotencyKey, 0, 111);
    }

    private function error(string $code, int $status): array
    {
        return [
            'ok' => false,
            'code' => $code,
            'status' => $status,
        ];
    }

    private function ownerDisplayName(?string $ownerCode, array $payload = []): string
    {
        $explicit = trim((string) ($payload['actorName'] ?? $payload['buyerName'] ?? $payload['sellerName'] ?? ''));
        if ($explicit !== '' && strtoupper($explicit) !== strtoupper((string) $ownerCode)) {
            return $explicit;
        }

        return (string) $ownerCode;
    }

    private function upsertTemplateQuantity(
        int $campaignId,
        int $containerId,
        int $templateId,
        int $quantity,
        ?int $priceOverride
    ): bool {
        $query = $this->db->query(
            'INSERT INTO shop_container_template_items (campaign_id, container_id, template_id, quantity, price_override, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                quantity = IF(shop_container_template_items.quantity IS NULL, NULL, shop_container_template_items.quantity + VALUES(quantity)),
                updated_at = NOW()',
            [$campaignId, $containerId, $templateId, $quantity, $priceOverride]
        );

        return (bool) $query;
    }
}
