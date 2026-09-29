<?php

namespace App\Services\Character;

use App\Models\ShopOwnerClaimModel;
use App\Models\ShopOwnerWalletBalanceModel;
use App\Services\Shop\ShopCurrencyService;
use App\Services\Shop\ShopWalletService;

final class CharacterWalletService
{
    private $db;
    private $characters;
    private $claims;
    private $currencies;
    private $wallets;
    private $walletBalances;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->characters = new CharacterDirectoryService();
        $this->claims = new ShopOwnerClaimModel($this->db);
        $this->currencies = new ShopCurrencyService();
        $this->wallets = new ShopWalletService();
        $this->walletBalances = new ShopOwnerWalletBalanceModel($this->db);
    }

    public function get(array $auth, int $campaignId, int $characterId): array
    {
        $character = $this->characters->show($auth, $characterId, $campaignId);
        $context = $this->currencies->getCampaignCurrencyContext($campaignId);
        $ownerCode = $this->ownerCode($campaignId, $characterId);
        $balanceMap = $this->wallets->getBalanceMap($campaignId, $ownerCode);
        $primaryCurrencyCode = strtolower((string) (
            $character['primaryCurrencyCode']
            ?? $context['defaultCurrencyCode']
            ?? 'generic'
        ));
        $wallets = [];
        foreach ((array) ($context['currencies'] ?? []) as $currency) {
            $code = strtolower((string) ($currency['code'] ?? ''));
            if ($code === '' || (!array_key_exists($code, $balanceMap) && $code !== $primaryCurrencyCode)) {
                continue;
            }
            $wallets[] = [
                'currencyCode' => $code,
                'balance' => max(0, (int) ($balanceMap[$code] ?? 0)),
            ];
        }

        return [
            'characterId' => $characterId,
            'ownerCode' => $ownerCode,
            'primaryCurrencyCode' => $primaryCurrencyCode,
            'revision' => max(1, (int) ($character['revision'] ?? 1)),
            'updatedAt' => $character['updatedAt'] ?? null,
            'currencies' => array_values((array) ($context['currencies'] ?? [])),
            'wallets' => $wallets,
            'capabilities' => [
                'canEdit' => !empty($character['capabilities']['canEdit']),
            ],
        ];
    }

    public function update(
        array $auth,
        int $campaignId,
        int $characterId,
        array $payload
    ): array {
        $this->characters->assertEditable($auth, $characterId, $campaignId);
        $context = $this->currencies->getCampaignCurrencyContext($campaignId);
        $allowed = array_map(
            static fn (array $currency): string => strtolower((string) ($currency['code'] ?? '')),
            (array) ($context['currencies'] ?? [])
        );
        $balances = self::validateWalletsPayload($payload, $allowed);
        $primaryCurrencyCode = strtolower(trim((string) ($payload['primaryCurrencyCode'] ?? '')));
        if ($primaryCurrencyCode === '' || !array_key_exists($primaryCurrencyCode, $balances)) {
            throw new CharacterException(
                'validation_failed',
                'The default wallet must be one of the saved wallets.',
                422,
                ['primaryCurrencyCode' => 'Choose one of the character wallets.']
            );
        }
        $ownerCode = $this->ownerCode($campaignId, $characterId);

        $this->db->transBegin();
        try {
            foreach ($balances as $currencyCode => $balance) {
                $this->wallets->setBalance($campaignId, $ownerCode, $currencyCode, $balance);
            }
            $updated = $this->db->table('characters')
                ->where('id', $characterId)
                ->update([
                    'primary_currency_code' => $primaryCurrencyCode,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            if (!$updated) {
                throw new CharacterException(
                    'wallet_write_failed',
                    'The default character wallet could not be saved.',
                    500
                );
            }
            $this->wallets->setBalance(
                $campaignId,
                $ownerCode,
                $primaryCurrencyCode,
                $balances[$primaryCurrencyCode]
            );
            $removedCodes = array_values(array_diff($allowed, array_keys($balances)));
            if ($removedCodes) {
                $this->walletBalances
                    ->where('campaign_id', $campaignId)
                    ->where('owner_code', $ownerCode)
                    ->whereIn('currency_code', $removedCodes)
                    ->delete();
            }
            if ($this->db->transStatus() === false) {
                throw new CharacterException(
                    'wallet_write_failed',
                    'Character wallets could not be saved.',
                    500
                );
            }
            $this->db->transCommit();
        } catch (CharacterException $exception) {
            $this->db->transRollback();
            throw $exception;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw new CharacterException(
                'wallet_write_failed',
                'Character wallets could not be saved.',
                500
            );
        }

        return $this->get($auth, $campaignId, $characterId);
    }

    public static function validateWalletsPayload(array $payload, array $allowedCodes): array
    {
        $rows = $payload['wallets'] ?? null;
        if (!is_array($rows)) {
            throw new CharacterException(
                'validation_failed',
                'Wallets must be an array.',
                422,
                ['wallets' => 'An array of wallets is required.']
            );
        }
        if (!$rows) {
            throw new CharacterException(
                'validation_failed',
                'At least one wallet is required.',
                422,
                ['wallets' => 'At least one wallet is required.']
            );
        }
        $allowed = array_fill_keys(array_filter(array_map(
            static fn ($code): string => strtolower(trim((string) $code)),
            $allowedCodes
        )), true);
        $result = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw self::invalidWallet($index);
            }
            $code = strtolower(trim((string) ($row['currencyCode'] ?? '')));
            $rawBalance = $row['balance'] ?? null;
            $balance = filter_var(
                $rawBalance,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0, 'max_range' => 2147483647]]
            );
            if ($code === '' || !isset($allowed[$code]) || $balance === false || isset($result[$code])) {
                throw self::invalidWallet($index);
            }
            $result[$code] = (int) $balance;
        }
        return $result;
    }

    private static function invalidWallet(int $index): CharacterException
    {
        return new CharacterException(
            'validation_failed',
            'Character wallet is invalid.',
            422,
            ["wallets.{$index}" => 'Currency and a non-negative integer balance are required.']
        );
    }

    private function ownerCode(int $campaignId, int $characterId): string
    {
        $claim = $this->claims
            ->where('campaign_id', $campaignId)
            ->where('character_id', $characterId)
            ->orderBy('id', 'ASC')
            ->first();
        $code = strtoupper(trim((string) ($claim['owner_code'] ?? '')));
        return $code !== '' ? $code : 'CHAR_' . $characterId;
    }
}
