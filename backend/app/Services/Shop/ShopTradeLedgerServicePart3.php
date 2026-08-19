<?php

namespace App\Services\Shop;

use App\Models\ShopModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopTradeTransactionModel;

trait ShopTradeLedgerServicePart3
{
    private function appendHistory(array $transaction, string $action, string $performedBy, array $payload = []): array
    {
        $history = array_values((array) ($transaction['history_json'] ?? []));
        $history[] = [
            'action' => $action,
            'performedBy' => $performedBy,
            'createdAt' => date('Y-m-d H:i:s'),
            'reason' => (string) ($payload['reason'] ?? $payload['correctionReason'] ?? ''),
            'gmNote' => (string) ($payload['gmNote'] ?? ''),
            'payload' => $payload,
        ];
        return $history;
    }

    private function performedBy(array $authContext): string
    {
        $id = $authContext['user_id'] ?? null;
        if ($id) {
            return 'user:' . (int) $id;
        }
        return 'GM';
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower($status);
        if ($status === 'success') {
            return 'executed';
        }
        if ($status === 'failed') {
            return 'failed';
        }
        return in_array($status, ['executed', 'reversed', 'redone', 'corrected', 'failed'], true)
            ? $status
            : 'failed';
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
