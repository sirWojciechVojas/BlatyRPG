<?php

namespace App\Services\Shop;

use App\Models\ShopModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopTradeTransactionModel;

trait ShopTradeLedgerServicePart1
{
    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->transactionModel = new ShopTradeTransactionModel();
        $this->shopModel = new ShopModel();
        $this->templateModel = new ShopTemplateModel();
        $this->snapshotService = new ShopTradeSnapshotService();
        $this->tradeService = new ShopTradeService();
    }

    public function list(int $campaignId, array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = max(1, min(200, (int) ($filters['pageSize'] ?? 50)));
        $total = (int) $this->buildLedgerQuery($campaignId, $filters)->countAllResults();
        $rows = $this->buildLedgerQuery($campaignId, $filters)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll($pageSize, ($page - 1) * $pageSize);
        $items = array_values(array_filter(array_map(function (array $row) use ($filters): ?array {
            $entry = $this->normalizeRow($row);
            if (!empty($filters['onlyReversed']) && $entry['status'] !== 'reversed') {
                return null;
            }
            if (!empty($filters['onlyRedone']) && !$entry['redoneBy'] && $entry['transactionType'] !== 'REDO') {
                return null;
            }
            if (!empty($filters['onlyCorrected']) && $entry['status'] !== 'corrected') {
                return null;
            }
            return $entry;
        }, $rows)));

        $summaryItems = array_values(array_filter(array_map(function (array $row) use ($filters): ?array {
            $entry = $this->normalizeRow($row);
            if (!empty($filters['onlyReversed']) && $entry['status'] !== 'reversed') return null;
            if (!empty($filters['onlyRedone']) && !$entry['redoneBy'] && $entry['transactionType'] !== 'REDO') return null;
            if (!empty($filters['onlyCorrected']) && $entry['status'] !== 'corrected') return null;
            return $entry;
        }, $this->buildLedgerQuery($campaignId, $filters)->findAll())));

        return [
            'count' => count($items),
            'items' => $items,
            'summary' => $this->summarize($summaryItems),
            'summaryScope' => 'filtered_result',
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'pageCount' => max(1, (int) ceil($total / $pageSize)),
                'hasPreviousPage' => $page > 1,
                'hasNextPage' => $page * $pageSize < $total,
            ],
        ];
    }

    private function buildLedgerQuery(int $campaignId, array $filters): ShopTradeTransactionModel
    {
        $query = (new ShopTradeTransactionModel())->where('campaign_id', $campaignId);
        if (!empty($filters['shopId'])) $query->where('shop_id', (int) $filters['shopId']);
        if (!empty($filters['ownerCode'])) $query->where('owner_code', strtoupper((string) $filters['ownerCode']));
        if (!empty($filters['transactionType'])) $query->where('transaction_type', strtoupper((string) $filters['transactionType']));
        if (!empty($filters['status'])) $query->where('status', strtolower((string) $filters['status']));
        if (!empty($filters['dateFrom'])) $query->where('created_at >=', (string) $filters['dateFrom']);
        if (!empty($filters['dateTo'])) $query->where('created_at <=', (string) $filters['dateTo']);
        if (!empty($filters['item'])) $query->like('item_name', (string) $filters['item']);
        if (!empty($filters['onlyReversed'])) $query->where('status', 'reversed');
        if (!empty($filters['onlyCorrected'])) $query->where('status', 'corrected');
        if (!empty($filters['onlyRedone'])) {
            $query->groupStart()->where('transaction_type', 'REDO')->orWhere('redone_by !=', '')->groupEnd();
        }
        return $query;
    }

    public function reverse(int $campaignId, int $transactionId, array $authContext, array $payload = []): array
    {
        $transaction = $this->findTransaction($campaignId, $transactionId);
        if (!$transaction) {
            return $this->error('not_found', 404);
        }
        if ($this->normalizeStatus((string) ($transaction['status'] ?? '')) === 'reversed') {
            return $this->error('already_reversed', 409);
        }

        $before = (array) ($transaction['before_snapshot_json'] ?? []);
        if (!$before) {
            return $this->error('missing_snapshot', 409);
        }

        $after = (array) ($transaction['after_snapshot_json'] ?? []);
        if (empty($payload['force']) && $this->snapshotService->currentDiffersFromSnapshot($after)) {
            return $this->error('snapshot_conflict', 409);
        }

        $this->db->transBegin();
        $applied = $this->snapshotService->apply($before);
        if (!($applied['ok'] ?? false)) {
            $this->db->transRollback();
            return $applied;
        }

        $now = date('Y-m-d H:i:s');
        $performedBy = $this->performedBy($authContext);
        $history = $this->appendHistory($transaction, 'reversed', $performedBy, $payload);
        $this->transactionModel->update($transactionId, [
            'status' => 'reversed',
            'reversed_by' => $performedBy,
            'history_json' => $history,
            'updated_at' => $now,
        ]);
        $auditId = $this->insertAuditRow($campaignId, $transaction, [
            'transaction_type' => 'REVERSE',
            'status' => 'reversed',
            'performed_by' => $performedBy,
            'reversed_by' => $performedBy,
            'gm_note' => (string) ($payload['gmNote'] ?? ''),
            'correction_reason' => (string) ($payload['reason'] ?? ''),
            'history_json' => $history,
            'before_snapshot_json' => $transaction['after_snapshot_json'] ?? null,
            'after_snapshot_json' => $before,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (!$this->db->transStatus()) {
            $this->db->transRollback();
            return $this->error('transaction_failed', 500);
        }
        $this->db->transCommit();

        return [
            'ok' => true,
            'transaction' => $this->normalizeRow($this->transactionModel->find($transactionId)),
            'auditTransactionId' => $auditId,
        ];
    }

    public function redo(int $campaignId, int $transactionId, array $authContext, array $payload = []): array
    {
        $transaction = $this->findTransaction($campaignId, $transactionId);
        if (!$transaction) {
            return $this->error('not_found', 404);
        }
        $type = strtoupper((string) ($transaction['transaction_type'] ?? ''));
        if (!in_array($type, ['BUY', 'SELL'], true)) {
            return $this->error('unsupported_transaction_type', 409);
        }

        $conditions = (array) ($transaction['conditions_snapshot_json'] ?? []);
        $ownerCode = strtoupper((string) ($conditions['ownerCode'] ?? $transaction['owner_code'] ?? ''));
        $shopId = (int) ($conditions['shopId'] ?? $transaction['shop_id'] ?? 0);
        $selections = $this->selectionsFromTransaction($transaction);
        if (!$ownerCode || !$shopId || !$selections) {
            return $this->error('invalid_snapshot', 409);
        }

        $currentDiffers = $this->snapshotService->currentDiffersFromSnapshot((array) ($transaction['after_snapshot_json'] ?? []));
        $redoPayload = [
            'ownerCode' => $ownerCode,
            'shopId' => $shopId,
            'selections' => $selections,
            'gmNote' => (string) ($payload['gmNote'] ?? $transaction['gm_note'] ?? ''),
            'correctionReason' => (string) ($payload['reason'] ?? $transaction['correction_reason'] ?? ''),
            'parentTransactionId' => (int) $transaction['id'],
            'forcedPrices' => true,
        ];

        $result = $type === 'BUY'
            ? $this->tradeService->buy($campaignId, $redoPayload, $authContext, null)
            : $this->tradeService->sell($campaignId, $redoPayload, $authContext, null);

        if (!($result['ok'] ?? false)) {
            return $result;
        }

        $performedBy = $this->performedBy($authContext);
        $history = $this->appendHistory($transaction, 'redone', $performedBy, array_merge($payload, [
            'currentDiffersFromSnapshot' => $currentDiffers,
        ]));
        $this->transactionModel->update((int) $transaction['id'], [
            'status' => 'redone',
            'redone_by' => $performedBy,
            'history_json' => $history,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'ok' => true,
            'currentDiffersFromSnapshot' => $currentDiffers,
            'result' => $result,
        ];
    }
}
