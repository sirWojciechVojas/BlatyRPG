<?php

namespace App\Services\Shop;

use App\Models\ShopOwnerWalletBalanceModel;
use App\Models\ShopOwnerWalletModel;
use App\Models\ShopOwnerClaimModel;
use App\Models\CharacterModel;

class ShopWalletService
{
    private $db;
    private $balanceModel;
    private $legacyWalletModel;
    private $currencyService;
    private $ownerClaimModel;
    private $characterModel;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->balanceModel = new ShopOwnerWalletBalanceModel();
        $this->legacyWalletModel = new ShopOwnerWalletModel();
        $this->currencyService = new ShopCurrencyService();
        $this->ownerClaimModel = new ShopOwnerClaimModel();
        $this->characterModel = new CharacterModel();
    }

    public function getBalances(int $campaignId, string $ownerCode): array
    {
        $ownerCode = strtoupper(trim($ownerCode));
        $this->ensurePrimaryBalanceFromLegacy($campaignId, $ownerCode);

        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'currencyCode' => strtolower((string) $row['currency_code']),
                'balance' => max(0, (int) $row['balance']),
            ];
        }, $this->balanceModel
            ->where('campaign_id', $campaignId)
            ->where('owner_code', $ownerCode)
            ->orderBy('id', 'ASC')
            ->findAll());
    }

    public function getBalanceMap(int $campaignId, string $ownerCode): array
    {
        $result = [];
        foreach ($this->getBalances($campaignId, $ownerCode) as $wallet) {
            $result[$wallet['currencyCode']] = (int) $wallet['balance'];
        }
        return $result;
    }

    /**
     * Ensures and locks wallet rows in a deterministic order. Must be called
     * inside the transaction which will perform the debits.
     */
    public function lockBalances(int $campaignId, string $ownerCode, array $currencyCodes): array
    {
        $ownerCode = strtoupper(trim($ownerCode));
        $codes = array_values(array_unique(array_filter(array_map(
            fn ($code): string => $this->normalizeCurrencyCode($campaignId, (string) $code),
            $currencyCodes
        ))));
        sort($codes, SORT_STRING);
        foreach ($codes as $code) {
            $this->getOrCreateBalance($campaignId, $ownerCode, $code);
        }
        if (!$codes) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $rows = $this->db->query(
            "SELECT id, currency_code, balance FROM shop_owner_wallet_balances "
            . "WHERE campaign_id = ? AND owner_code = ? AND currency_code IN ({$placeholders}) "
            . 'ORDER BY currency_code ASC FOR UPDATE',
            array_merge([$campaignId, $ownerCode], $codes)
        )->getResultArray();
        $result = [];
        foreach ($rows as $row) {
            $code = strtolower((string) $row['currency_code']);
            $result[$code] = [
                'id' => (int) $row['id'],
                'currencyCode' => $code,
                'balance' => max(0, (int) $row['balance']),
                'campaignId' => $campaignId,
                'ownerCode' => $ownerCode,
                'characterId' => $this->characterIdForOwner($campaignId, $ownerCode),
                'usesCharacterBrass' => $code === $this->primaryCurrencyCode($campaignId, $ownerCode),
            ];
        }
        return $result;
    }

    public function debitBreakdown(array $lockedWallets, array $debits): bool
    {
        foreach ($debits as $debit) {
            $currencyCode = strtolower((string) ($debit['currencyCode'] ?? ''));
            $amount = max(0, (int) ($debit['debit'] ?? 0));
            if ($amount === 0) {
                continue;
            }
            if (!isset($lockedWallets[$currencyCode]) || !$this->debit($lockedWallets[$currencyCode], $amount)) {
                return false;
            }
        }
        return true;
    }

    public function getOrCreateBalance(int $campaignId, string $ownerCode, string $currencyCode): array
    {
        $ownerCode = strtoupper(trim($ownerCode));
        $currencyCode = $this->normalizeCurrencyCode($campaignId, $currencyCode);
        $this->ensurePrimaryBalanceFromLegacy($campaignId, $ownerCode);

        $wallet = $this->balanceModel
            ->where('campaign_id', $campaignId)
            ->where('owner_code', $ownerCode)
            ->where('currency_code', $currencyCode)
            ->first();
        if (!$wallet) {
            $this->balanceModel->insert([
                'campaign_id' => $campaignId,
                'owner_code' => $ownerCode,
                'currency_code' => $currencyCode,
                'balance' => 0,
            ]);
            $wallet = $this->balanceModel->find((int) $this->balanceModel->getInsertID());
        }

        return [
            'id' => (int) $wallet['id'],
            'currencyCode' => $currencyCode,
            'balance' => max(0, (int) $wallet['balance']),
            'campaignId' => $campaignId,
            'ownerCode' => $ownerCode,
            'characterId' => $this->characterIdForOwner($campaignId, $ownerCode),
            'usesCharacterBrass' => $currencyCode === $this->primaryCurrencyCode($campaignId, $ownerCode),
        ];
    }

    public function debit(array $wallet, int $amount): bool
    {
        $amount = max(0, $amount);
        if (!empty($wallet['usesCharacterBrass']) && !empty($wallet['characterId'])) {
            $updated = $this->db->query(
                'UPDATE shop_owner_wallet_balances AS wallet '
                . 'INNER JOIN characters AS character_row ON character_row.id = ? '
                . 'SET wallet.balance = wallet.balance - ?, '
                . 'wallet.updated_at = ?, character_row.brass = character_row.brass - ?, character_row.updated_at = ? '
                . 'WHERE wallet.id = ? AND wallet.balance >= ? AND character_row.brass >= ?',
                [
                    (int) $wallet['characterId'],
                    $amount,
                    date('Y-m-d H:i:s'),
                    $amount,
                    date('Y-m-d H:i:s'),
                    (int) $wallet['id'],
                    $amount,
                    $amount,
                ]
            );
            return (bool) $updated && $this->db->affectedRows() >= 1;
        }
        $updated = $this->db->query(
            'UPDATE shop_owner_wallet_balances SET balance = balance - ?, updated_at = ? WHERE id = ? AND balance >= ?',
            [$amount, date('Y-m-d H:i:s'), (int) $wallet['id'], $amount]
        );
        return (bool) $updated && $this->db->affectedRows() === 1;
    }

    public function credit(array $wallet, int $amount): bool
    {
        $amount = max(0, $amount);
        if (!empty($wallet['usesCharacterBrass']) && !empty($wallet['characterId'])) {
            $updated = $this->db->query(
                'UPDATE shop_owner_wallet_balances AS wallet '
                . 'INNER JOIN characters AS character_row ON character_row.id = ? '
                . 'SET wallet.balance = wallet.balance + ?, '
                . 'wallet.updated_at = ?, character_row.brass = character_row.brass + ?, character_row.updated_at = ? '
                . 'WHERE wallet.id = ?',
                [
                    (int) $wallet['characterId'],
                    $amount,
                    date('Y-m-d H:i:s'),
                    $amount,
                    date('Y-m-d H:i:s'),
                    (int) $wallet['id'],
                ]
            );
            return (bool) $updated && $this->db->affectedRows() >= 1;
        }
        $updated = $this->db->query(
            'UPDATE shop_owner_wallet_balances SET balance = balance + ?, updated_at = ? WHERE id = ?',
            [$amount, date('Y-m-d H:i:s'), (int) $wallet['id']]
        );
        return (bool) $updated && $this->db->affectedRows() === 1;
    }

    public function setBalance(int $campaignId, string $ownerCode, string $currencyCode, int $balance): void
    {
        $wallet = $this->getOrCreateBalance($campaignId, $ownerCode, $currencyCode);
        $balance = max(0, $balance);
        $this->balanceModel->update((int) $wallet['id'], ['balance' => $balance]);
        if (!empty($wallet['usesCharacterBrass']) && !empty($wallet['characterId'])) {
            $this->characterModel->update((int) $wallet['characterId'], ['brass' => $balance]);
        }
        $this->syncLegacyDefaultBalance($campaignId, $ownerCode, $currencyCode, $balance);
    }

    public function syncLegacyDefaultBalance(int $campaignId, string $ownerCode, string $currencyCode, int $balance): void
    {
        if ($this->primaryCurrencyCode($campaignId, $ownerCode) !== strtolower($currencyCode)) {
            return;
        }
        $ownerCode = strtoupper(trim($ownerCode));
        $characterId = $this->characterIdForOwner($campaignId, $ownerCode);
        if ($characterId) {
            $this->characterModel->update($characterId, ['brass' => max(0, $balance)]);
        }
        $legacy = $this->legacyWalletModel
            ->where('campaign_id', $campaignId)
            ->where('owner_code', $ownerCode)
            ->first();
        if ($legacy) {
            $this->legacyWalletModel->update((int) $legacy['id'], [
                'brass_balance' => max(0, $balance),
            ]);
            return;
        }
        $this->legacyWalletModel->insert([
            'campaign_id' => $campaignId,
            'owner_code' => $ownerCode,
            'brass_balance' => max(0, $balance),
        ]);
    }

    public function defaultCurrencyCode(int $campaignId): string
    {
        return strtolower((string) (
            $this->currencyService->getCampaignCurrencyContext($campaignId)['defaultCurrencyCode'] ?? 'generic'
        ));
    }

    public function primaryCurrencyCode(int $campaignId, string $ownerCode): string
    {
        $character = $this->characterForOwner($campaignId, $ownerCode);
        $code = strtolower(trim((string) ($character['primary_currency_code'] ?? '')));
        return $code !== '' ? $code : $this->defaultCurrencyCode($campaignId);
    }

    private function ensurePrimaryBalanceFromLegacy(int $campaignId, string $ownerCode): void
    {
        $defaultCurrencyCode = $this->primaryCurrencyCode($campaignId, $ownerCode);
        $character = $this->characterForOwner($campaignId, $ownerCode);
        $existing = $this->balanceModel
            ->where('campaign_id', $campaignId)
            ->where('owner_code', $ownerCode)
            ->where('currency_code', $defaultCurrencyCode)
            ->first();
        if ($existing) {
            if ($character && (int) $existing['balance'] !== max(0, (int) ($character['brass'] ?? 0))) {
                $this->balanceModel->update((int) $existing['id'], [
                    'balance' => max(0, (int) ($character['brass'] ?? 0)),
                ]);
            }
            return;
        }
        $legacy = $this->legacyWalletModel
            ->where('campaign_id', $campaignId)
            ->where('owner_code', $ownerCode)
            ->first();
        $this->balanceModel->insert([
            'campaign_id' => $campaignId,
            'owner_code' => $ownerCode,
            'currency_code' => $defaultCurrencyCode,
            'balance' => $character
                ? max(0, (int) ($character['brass'] ?? 0))
                : max(0, (int) ($legacy['brass_balance'] ?? 0)),
        ]);
    }

    private function characterIdForOwner(int $campaignId, string $ownerCode): ?int
    {
        $character = $this->characterForOwner($campaignId, $ownerCode);
        return $character ? (int) $character['id'] : null;
    }

    private function characterForOwner(int $campaignId, string $ownerCode): ?array
    {
        $ownerCode = strtoupper(trim($ownerCode));
        $claim = $this->ownerClaimModel
            ->where('campaign_id', $campaignId)
            ->where('owner_code', $ownerCode)
            ->first();
        if (!empty($claim['character_id'])) {
            return $this->characterModel->find((int) $claim['character_id']) ?: null;
        }

        if (preg_match('/^CHAR_(\d+)$/', $ownerCode, $matches) !== 1) {
            return null;
        }
        $character = $this->characterModel->find((int) $matches[1]);
        if (!$character) {
            return null;
        }
        $characterCampaignId = isset($character['campaign_id']) ? (int) $character['campaign_id'] : 0;
        return $characterCampaignId === 0 || $characterCampaignId === $campaignId ? $character : null;
    }

    private function normalizeCurrencyCode(int $campaignId, string $currencyCode): string
    {
        $normalized = strtolower(trim($currencyCode));
        return $normalized !== '' ? substr($normalized, 0, 64) : $this->defaultCurrencyCode($campaignId);
    }
}
