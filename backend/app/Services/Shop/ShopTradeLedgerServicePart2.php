<?php

namespace App\Services\Shop;

use App\Models\ShopModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopTradeTransactionModel;

trait ShopTradeLedgerServicePart2
{
    public function correct(int $campaignId, int $transactionId, array $authContext, array $payload = []): array
    {
        $transaction = $this->findTransaction($campaignId, $transactionId);
        if (!$transaction) {
            return $this->error('not_found', 404);
        }

        $conditions = (array) ($transaction['conditions_snapshot_json'] ?? []);
        $items = array_values((array) ($conditions['items'] ?? []));
        $quantity = max(1, (int) ($payload['quantity'] ?? $transaction['quantity'] ?? 1));
        $finalPrice = max(0, (int) ($payload['finalPrice'] ?? $transaction['final_price'] ?? 0));
        $basePrice = max(0, (int) ($payload['basePrice'] ?? $transaction['base_price'] ?? $finalPrice));

        foreach ($items as &$item) {
            $item['quantity'] = $quantity;
            $item['unitPrice'] = $finalPrice;
            $item['basePrice'] = $basePrice;
            $item['pricing']['finalPrice'] = $finalPrice;
            $item['pricing']['basePrice'] = $basePrice;
            $item['pricing']['gmAdjustment'] = [
                'discountOrMarkup' => (string) ($payload['discountOrMarkup'] ?? ''),
                'buyRate' => (string) ($payload['buyRate'] ?? ''),
                'sellRate' => (string) ($payload['sellRate'] ?? ''),
                'source' => (string) ($payload['itemSource'] ?? ''),
            ];
        }
        unset($item);

        $conditions = array_merge($conditions, [
            'shopId' => (int) ($payload['shopId'] ?? $conditions['shopId'] ?? $transaction['shop_id']),
            'ownerCode' => strtoupper((string) ($payload['buyerId'] ?? $payload['actorId'] ?? $conditions['ownerCode'] ?? $transaction['owner_code'])),
            'items' => $items,
            'gmChangedAt' => date('Y-m-d H:i:s'),
        ]);

        $performedBy = $this->performedBy($authContext);
        $history = $this->appendHistory($transaction, 'corrected', $performedBy, $payload);
        $now = date('Y-m-d H:i:s');
        $id = $this->insertAuditRow($campaignId, $transaction, [
            'transaction_type' => strtoupper((string) ($transaction['transaction_type'] ?? 'BUY')),
            'status' => 'corrected',
            'shop_id' => (int) ($conditions['shopId'] ?? $transaction['shop_id']),
            'owner_code' => (string) ($conditions['ownerCode'] ?? $transaction['owner_code']),
            'actor_id' => (string) ($payload['actorId'] ?? $transaction['actor_id'] ?? $transaction['owner_code']),
            'actor_name' => (string) ($payload['actorName'] ?? $transaction['actor_name'] ?? $transaction['owner_code']),
            'seller_id' => (string) ($payload['sellerId'] ?? $transaction['seller_id'] ?? ''),
            'seller_name' => (string) ($payload['sellerName'] ?? $transaction['seller_name'] ?? ''),
            'buyer_id' => (string) ($payload['buyerId'] ?? $transaction['buyer_id'] ?? ''),
            'buyer_name' => (string) ($payload['buyerName'] ?? $transaction['buyer_name'] ?? ''),
            'quantity' => $quantity,
            'base_price' => $basePrice,
            'final_price' => $finalPrice,
            'total_brass' => $quantity * $finalPrice,
            'price_modifiers_json' => [
                'discountOrMarkup' => (string) ($payload['discountOrMarkup'] ?? ''),
                'buyRate' => (string) ($payload['buyRate'] ?? ''),
                'sellRate' => (string) ($payload['sellRate'] ?? ''),
            ],
            'conditions_snapshot_json' => $conditions,
            'correction_reason' => (string) ($payload['correctionReason'] ?? $payload['reason'] ?? ''),
            'gm_note' => (string) ($payload['gmNote'] ?? ''),
            'performed_by' => $performedBy,
            'history_json' => $history,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'ok' => true,
            'transaction' => $this->normalizeRow($this->transactionModel->find($id)),
        ];
    }

    private function findTransaction(int $campaignId, int $transactionId): ?array
    {
        return $this->transactionModel
            ->where('campaign_id', $campaignId)
            ->where('id', $transactionId)
            ->first();
    }

    private function normalizeRow(?array $row): array
    {
        $row = $row ?: [];
        $status = $this->normalizeStatus((string) ($row['status'] ?? 'failed'));
        $base = (int) ($row['base_price'] ?? 0);
        $final = (int) ($row['final_price'] ?? $row['total_brass'] ?? 0);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? $row['created_at'] ?? ''),
            'shopId' => isset($row['shop_id']) ? (int) $row['shop_id'] : null,
            'shopName' => (string) ($row['shop_name'] ?? ''),
            'actorId' => (string) ($row['actor_id'] ?? $row['owner_code'] ?? ''),
            'actorName' => (string) ($row['actor_name'] ?? $row['owner_code'] ?? ''),
            'sellerId' => (string) ($row['seller_id'] ?? ''),
            'sellerName' => (string) ($row['seller_name'] ?? ''),
            'buyerId' => (string) ($row['buyer_id'] ?? ''),
            'buyerName' => (string) ($row['buyer_name'] ?? ''),
            'transactionType' => strtoupper((string) ($row['transaction_type'] ?? '')),
            'status' => $status,
            'itemId' => (string) ($row['item_id'] ?? ''),
            'itemTemplateId' => isset($row['item_template_id']) ? (int) $row['item_template_id'] : null,
            'itemName' => (string) ($row['item_name'] ?? ''),
            'quantity' => (int) ($row['quantity'] ?? 0),
            'basePrice' => $base,
            'finalPrice' => $final,
            'difference' => $final - $base,
            'currency' => (string) ($row['currency'] ?? 'brass'),
            'priceModifiers' => (array) ($row['price_modifiers_json'] ?? []),
            'conditionsSnapshot' => (array) ($row['conditions_snapshot_json'] ?? []),
            'beforeSnapshot' => (array) ($row['before_snapshot_json'] ?? []),
            'afterSnapshot' => (array) ($row['after_snapshot_json'] ?? []),
            'parentTransactionId' => isset($row['parent_transaction_id']) ? (int) $row['parent_transaction_id'] : null,
            'correctionReason' => (string) ($row['correction_reason'] ?? ''),
            'gmNote' => (string) ($row['gm_note'] ?? ''),
            'performedBy' => (string) ($row['performed_by'] ?? ''),
            'reversedBy' => (string) ($row['reversed_by'] ?? ''),
            'redoneBy' => (string) ($row['redone_by'] ?? ''),
            'history' => array_values((array) ($row['history_json'] ?? [])),
            'payload' => (array) ($row['payload_json'] ?? []),
            'response' => (array) ($row['response_json'] ?? []),
        ];
    }

    private function summarize(array $items): array
    {
        $summary = [
            'transactionCount' => count($items),
            'purchasesTotal' => 0,
            'salesTotal' => 0,
            'shopBalance' => 0,
            'ownerBalance' => 0,
            'reversalCount' => 0,
            'redoCount' => 0,
            'gmCorrectionCount' => 0,
            'byCurrency' => [],
        ];

        foreach ($items as $item) {
            $value = (int) ($item['finalPrice'] ?? 0) * max(1, (int) ($item['quantity'] ?? 1));
            $currency = strtolower((string) ($item['currency'] ?? 'generic'));
            if (!isset($summary['byCurrency'][$currency])) {
                $summary['byCurrency'][$currency] = [
                    'purchasesTotal' => 0,
                    'salesTotal' => 0,
                    'shopBalance' => 0,
                    'ownerBalance' => 0,
                ];
            }
            if (($item['transactionType'] ?? '') === 'BUY') {
                $summary['purchasesTotal'] += $value;
                $summary['shopBalance'] += $value;
                $summary['ownerBalance'] -= $value;
                $summary['byCurrency'][$currency]['purchasesTotal'] += $value;
                $summary['byCurrency'][$currency]['shopBalance'] += $value;
                $summary['byCurrency'][$currency]['ownerBalance'] -= $value;
            }
            if (($item['transactionType'] ?? '') === 'SELL') {
                $summary['salesTotal'] += $value;
                $summary['shopBalance'] -= $value;
                $summary['ownerBalance'] += $value;
                $summary['byCurrency'][$currency]['salesTotal'] += $value;
                $summary['byCurrency'][$currency]['shopBalance'] -= $value;
                $summary['byCurrency'][$currency]['ownerBalance'] += $value;
            }
            if (($item['status'] ?? '') === 'reversed') {
                $summary['reversalCount']++;
            }
            if (($item['transactionType'] ?? '') === 'REDO' || !empty($item['redoneBy'])) {
                $summary['redoCount']++;
            }
            if (($item['status'] ?? '') === 'corrected') {
                $summary['gmCorrectionCount']++;
            }
        }

        return $summary;
    }

    private function selectionsFromTransaction(array $transaction): array
    {
        $conditions = (array) ($transaction['conditions_snapshot_json'] ?? []);
        $items = array_values((array) ($conditions['items'] ?? []));
        if (!$items && !empty($transaction['item_template_id'])) {
            $items = [[
                'templateId' => (int) $transaction['item_template_id'],
                'quantity' => max(1, (int) ($transaction['quantity'] ?? 1)),
                'unitPrice' => max(0, (int) ($transaction['final_price'] ?? $transaction['total_brass'] ?? 0)),
            ]];
        }

        return array_values(array_filter(array_map(static function (array $item): ?array {
            $templateId = (int) ($item['templateId'] ?? $item['template_id'] ?? 0);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            if (!$templateId) {
                return null;
            }
            return [
                'templateId' => $templateId,
                'quantity' => $quantity,
                'finalPrice' => max(0, (int) ($item['unitPrice'] ?? $item['finalPrice'] ?? 0)),
            ];
        }, $items)));
    }

    private function insertAuditRow(int $campaignId, array $source, array $overrides): int
    {
        $payload = [
            'campaign_id' => $campaignId,
            'transaction_uuid' => bin2hex(random_bytes(16)),
            'idempotency_key' => null,
            'actor_user_id' => $source['actor_user_id'] ?? null,
            'actor_id' => $source['actor_id'] ?? $source['owner_code'] ?? null,
            'actor_name' => $source['actor_name'] ?? $source['owner_code'] ?? null,
            'owner_code' => $source['owner_code'] ?? null,
            'shop_id' => $source['shop_id'] ?? null,
            'shop_name' => $source['shop_name'] ?? null,
            'seller_id' => $source['seller_id'] ?? null,
            'seller_name' => $source['seller_name'] ?? null,
            'buyer_id' => $source['buyer_id'] ?? null,
            'buyer_name' => $source['buyer_name'] ?? null,
            'transaction_type' => $source['transaction_type'] ?? 'AUDIT',
            'status' => 'executed',
            'error_code' => null,
            'item_id' => $source['item_id'] ?? null,
            'item_template_id' => $source['item_template_id'] ?? null,
            'item_name' => $source['item_name'] ?? null,
            'quantity' => $source['quantity'] ?? 0,
            'base_price' => $source['base_price'] ?? 0,
            'final_price' => $source['final_price'] ?? 0,
            'currency' => $source['currency'] ?? 'brass',
            'price_modifiers_json' => $source['price_modifiers_json'] ?? [],
            'conditions_snapshot_json' => $source['conditions_snapshot_json'] ?? [],
            'before_snapshot_json' => $source['before_snapshot_json'] ?? [],
            'after_snapshot_json' => $source['after_snapshot_json'] ?? [],
            'parent_transaction_id' => (int) ($source['id'] ?? 0),
            'correction_reason' => $source['correction_reason'] ?? null,
            'gm_note' => $source['gm_note'] ?? null,
            'performed_by' => $source['performed_by'] ?? null,
            'reversed_by' => $source['reversed_by'] ?? null,
            'redone_by' => $source['redone_by'] ?? null,
            'history_json' => $source['history_json'] ?? [],
            'total_brass' => $source['total_brass'] ?? 0,
            'payload_json' => $source['payload_json'] ?? [],
            'response_json' => $source['response_json'] ?? [],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->transactionModel->insert(array_merge($payload, $overrides));
        return (int) $this->transactionModel->getInsertID();
    }
}
