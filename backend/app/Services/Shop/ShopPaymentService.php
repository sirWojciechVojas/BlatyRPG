<?php

namespace App\Services\Shop;

class ShopPaymentService
{
    private const RATE_SCALE = 1000000;
    private const PERCENT_SCALE = 10000;

    public function quote(
        int $shopId,
        string $ownerCode,
        int $total,
        string $settlementCurrencyCode,
        array $currencyPolicy,
        array $balances,
        ?array $selectedCurrencyCodes = null,
        string $contextFingerprint = ''
    ): array {
        $settlement = strtolower(trim($settlementCurrencyCode)) ?: 'generic';
        $balanceMap = [];
        foreach ($balances as $key => $wallet) {
            if (is_array($wallet)) {
                $code = strtolower((string) ($wallet['currencyCode'] ?? $wallet['currency_code'] ?? $key));
                $balance = (int) ($wallet['balance'] ?? 0);
            } else {
                $code = strtolower((string) $key);
                $balance = (int) $wallet;
            }
            if ($code !== '') {
                $balanceMap[$code] = max(0, $balance);
            }
        }
        $balanceMap[$settlement] = max(0, (int) ($balanceMap[$settlement] ?? 0));

        $total = max(0, $total);
        $settlementDebit = min($total, $balanceMap[$settlement]);
        $remaining = $total - $settlementDebit;
        $feePercent = max(0.0, min(100.0, (float) ($currencyPolicy['paymentExchangeFeePercent'] ?? 5)));
        $feeBasisPoints = (int) round($feePercent * 100);
        $rates = is_array($currencyPolicy['exchangeRates'] ?? null)
            ? $currencyPolicy['exchangeRates']
            : [];
        $selected = $selectedCurrencyCodes === null ? null : array_fill_keys(array_map(
            static fn ($code): string => strtolower(trim((string) $code)),
            $selectedCurrencyCodes
        ), true);

        $foreign = [];
        foreach ($balanceMap as $code => $balance) {
            if ($code === $settlement || $balance <= 0 || !array_key_exists($code, $rates)) {
                continue;
            }
            $rateMicros = max(1, (int) round((float) $rates[$code] * self::RATE_SCALE));
            $denominator = self::RATE_SCALE * (self::PERCENT_SCALE + $feeBasisPoints);
            $maxCoverage = intdiv($balance * $rateMicros * self::PERCENT_SCALE, $denominator);
            $foreign[] = [
                'currencyCode' => $code,
                'availableBalance' => $balance,
                'exchangeRate' => $rateMicros / self::RATE_SCALE,
                'exchangeRateMicros' => $rateMicros,
                'feePercent' => $feePercent,
                'maxSettlementCoverage' => $maxCoverage,
                'selected' => $selected === null || isset($selected[$code]),
                'debit' => 0,
                'settlementCovered' => 0,
            ];
        }
        usort($foreign, static function (array $left, array $right): int {
            return ($right['maxSettlementCoverage'] <=> $left['maxSettlementCoverage'])
                ?: strcmp($left['currencyCode'], $right['currencyCode']);
        });

        foreach ($foreign as &$wallet) {
            if (!$wallet['selected'] || $remaining <= 0 || $wallet['maxSettlementCoverage'] <= 0) {
                continue;
            }
            $coverage = min($remaining, (int) $wallet['maxSettlementCoverage']);
            $numerator = $coverage * self::RATE_SCALE * (self::PERCENT_SCALE + $feeBasisPoints);
            $denominator = (int) $wallet['exchangeRateMicros'] * self::PERCENT_SCALE;
            $debit = intdiv($numerator + $denominator - 1, $denominator);
            $wallet['debit'] = min((int) $wallet['availableBalance'], $debit);
            $wallet['settlementCovered'] = $coverage;
            $remaining -= $coverage;
        }
        unset($wallet);

        $debits = [[
            'currencyCode' => $settlement,
            'debit' => $settlementDebit,
            'settlementCovered' => $settlementDebit,
            'exchangeRate' => 1.0,
            'feePercent' => 0.0,
        ]];
        foreach ($foreign as $wallet) {
            if ((int) $wallet['debit'] > 0) {
                $debits[] = [
                    'currencyCode' => $wallet['currencyCode'],
                    'debit' => (int) $wallet['debit'],
                    'settlementCovered' => (int) $wallet['settlementCovered'],
                    'exchangeRate' => $wallet['exchangeRate'],
                    'feePercent' => $wallet['feePercent'],
                ];
            }
        }

        $quote = [
            'shopId' => $shopId,
            'ownerCode' => strtoupper(trim($ownerCode)),
            'price' => $total,
            'settlementCurrencyCode' => $settlement,
            'settlementBalance' => $balanceMap[$settlement],
            'settlementDebit' => $settlementDebit,
            'remainingUncovered' => max(0, $remaining),
            'paymentExchangeFeePercent' => $feePercent,
            'foreignWallets' => $foreign,
            'debits' => $debits,
            'selectedCurrencyCodes' => array_values(array_map(
                static fn (array $wallet): string => $wallet['currencyCode'],
                array_filter($foreign, static fn (array $wallet): bool => $wallet['selected'])
            )),
            'canPay' => $remaining <= 0,
            'requiresConversion' => $total > $settlementDebit,
            'contextFingerprint' => $contextFingerprint,
        ];
        $quote['quoteFingerprint'] = hash('sha256', json_encode($quote, JSON_UNESCAPED_SLASHES));
        return $quote;
    }
}
