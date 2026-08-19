<?php

namespace App\Services\Shop;

trait ShopPricingServicePart8
{
    private function buildPricingContext(array $template, array $profile, array $item, string $mode): array
    {
        $resolvedMode = strtolower($mode) === 'sell' ? 'sell' : 'buy';
        $config = self::normalizePricingConfig($profile['pricingConfig'] ?? null);
        $sourcePrice = max(0, $this->number($template['prize'] ?? $template['PRIZE'] ?? 0));
        $sourceCurrency = strtolower(trim((string) (
            $template['currency_code'] ?? $template['CURRENCY'] ?? $template['currencyCode'] ?? 'generic'
        )));
        $settlement = strtolower(trim((string) ($config['currencyPolicy']['settlementCurrencyCode'] ?? '')));
        $settlement = $settlement ?: $sourceCurrency;
        // `generic` was used by legacy templates to mean the campaign's system
        // currency. Once a shop has a concrete settlement currency, it is not a
        // separate foreign currency and must not require an exchange rate.
        if ($sourceCurrency === '' || ($sourceCurrency === 'generic' && $settlement !== 'generic')) {
            $sourceCurrency = $settlement;
        }
        $configured = $sourceCurrency === $settlement ||
            array_key_exists($sourceCurrency, $config['currencyPolicy']['exchangeRates']);
        $rate = $sourceCurrency === $settlement
            ? 1.0
            : $this->number($config['currencyPolicy']['exchangeRates'][$sourceCurrency] ?? 1, 1);
        $fee = $sourceCurrency === $settlement ? 0.0 : $this->number(
            $config['currencyPolicy'][$resolvedMode === 'buy' ? 'buyFeePercent' : 'sellFeePercent'] ?? 0
        );
        $feeMultiplier = $resolvedMode === 'buy' ? 1 + ($fee / 100) : 1 - ($fee / 100);
        $stock = max(0, (int) round($this->number($item['stockQuantity'] ?? $item['quantity'] ?? $item['QUANTITY'] ?? 1)));
        $settings = ShopProfileSchemaService::normalizeSettings($profile['marketSettings'] ?? []);
        $actorCode = strtoupper((string) ($item['actorCode'] ?? $item['ownerCode'] ?? ''));
        $reputation = (string) (
            $item['reputation'] ??
            ($settings['reputationByActor'][$actorCode] ?? ($profile['reputation'] ?? 'neutralna'))
        );
        $condition = strtolower((string) ($item['condition'] ?? $item['CONDITION'] ?? 'good'));
        if (!in_array($condition, ['ruined', 'poor', 'worn', 'good', 'excellent'], true)) $condition = 'good';
        $demand = strtolower((string) ($item['demandLevel'] ?? $settings['demandLevel'] ?? 'normal'));
        if (!in_array($demand, ['very_low', 'low', 'normal', 'high', 'extreme'], true)) $demand = 'normal';
        $converted = $sourcePrice * $rate * max(0, $feeMultiplier);
        $asOf = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($item['asOf'] ?? ''))
            ? (string) $item['asOf']
            : date('Y-m-d');
        $sensitivity = $this->resolveCounterfeitSensitivity($template);
        $risk = $this->clamp($this->number($profile['counterfeitRisk'] ?? 10), 0, 100);
        $weights = ['low' => 0.18, 'medium' => 0.42, 'high' => 0.65];
        $probability = round($risk * ($weights[$sensitivity] ?? 0.18), 2);
        $seed = implode(':', [$profile['shopId'] ?? 0, $template['id'] ?? $template['ID'] ?? 0, $asOf]);
        $roll = (abs((int) crc32($seed)) % 10000) / 100;
        $counterfeit = [
            'probabilityPercent' => $probability,
            'deterministicRoll' => $roll,
            'suspected' => $probability > 0 && $roll < $probability,
            'sensitivity' => $sensitivity,
            'consequence' => $probability > 0 ? 'inspection_reputation_or_legal_consequence' : null,
        ];
        return [
            'mode' => $resolvedMode,
            'profile' => array_merge($profile, ['marketSettings' => $settings]),
            'template' => $template,
            'item' => $item,
            'pricingConfig' => $config,
            'basePrice' => $converted,
            'sourceBasePrice' => $sourcePrice,
            'sourceCurrencyCode' => $sourceCurrency,
            'settlementCurrencyCode' => $settlement,
            'exchangeRate' => $rate,
            'exchangeRateConfigured' => $configured,
            'exchangeFeePercent' => $fee,
            'currencyMultiplier' => $rate * max(0, $feeMultiplier),
            'stockQuantity' => $stock,
            'quantityRequested' => max(1, min(9999, (int) ($item['quantityRequested'] ?? 1))),
            'priceTier' => $this->resolvePriceTier($converted, $config['priceBands']),
            'itemLegality' => $this->resolveTemplateLegality($template),
            'counterfeitSensitivity' => $sensitivity,
            'counterfeit' => $counterfeit,
            'typeNode' => $this->typeNode((string) ($profile['typeId'] ?? '')),
            'worldProfile' => $this->worldProfile((string) ($profile['worldProfileId'] ?? 'standard')),
            'actorReputation' => $reputation,
            'condition' => $condition,
            'demandLevel' => $demand,
            'asOf' => $asOf,
            'entryAdjustmentPrice' => array_key_exists('manualPrice', $item)
                ? $item['manualPrice']
                : ($resolvedMode === 'buy' && (
                    $item['PRICE_OVERRIDE'] ?? $item['price_override'] ?? $item['PERSONAL_COST'] ?? null
                ) !== null
                    ? $this->number(
                        $item['PRICE_OVERRIDE'] ?? $item['price_override'] ?? $item['PERSONAL_COST'],
                        0
                    ) * $rate * max(0, $feeMultiplier)
                    : null),
            'temporaryModifier' => $item['temporaryModifier'] ?? 0,
        ];
    }

    private function appendFactor(
        float &$current,
        array &$breakdown,
        string $key,
        array $effect,
        array $extra = [],
        bool $enabled = true
    ): void {
        $applied = $enabled && !empty($effect['active']);
        $result = $applied ? $this->applyEffect($current, $effect) : $this->applyEffect($current, []);
        if ($applied) $current = is_finite($result['after']) ? $result['after'] : 0.0;
        $breakdown[] = $this->modifierRecord($key, $effect, $result, array_merge([
            'enabled' => $enabled,
            'applied' => $applied,
            'skippedReason' => $applied ? null : ($enabled ? ($effect['reason'] ?? 'not_applicable') : 'disabled'),
        ], $extra));
    }

    private function finalizeBreakdown(array $rows): array
    {
        $names = [
            'catalogPrice' => 'Cena katalogowa', 'currencyConversion' => 'Przeliczenie waluty',
            'saleBase' => 'Bazowa stawka sprzedaży', 'buybackBase' => 'Bazowa stawka skupu',
            'worldProfile' => 'Profil świata', 'shopType' => 'Typ sklepu',
            'location' => 'Lokalizacja i transport', 'availability' => 'Aktualny zapas',
            'demand' => 'Popyt', 'condition' => 'Stan przedmiotu', 'seasonality' => 'Sezonowość',
            'marketEvents' => 'Wydarzenia rynkowe', 'wealth' => 'Zamożność',
            'legality' => 'Legalność i ryzyko', 'counterfeitRisk' => 'Ryzyko fałszerstwa',
            'reputation' => 'Reputacja kupującego', 'pricingPolicy' => 'Polityka cenowa',
            'manualAdjustment' => 'Ręczna korekta MG', 'temporaryModifier' => 'Tymczasowy modyfikator',
            'guardrailRange' => 'Zakres bezpieczny', 'wealthBuybackLimit' => 'Limit kapitału skupu',
            'rounding' => 'Zaokrąglenie', 'minimumPrice' => 'Cena minimalna',
            'guardrailBuybackRatio' => 'Limit skupu względem sprzedaży',
            'finitePriceGuard' => 'Ochrona wartości ceny',
        ];
        foreach ($rows as $index => &$row) {
            $row['order'] = $index + 1;
            $row['name'] = strpos($row['key'], 'policyRule:') === 0
                ? ((string) ($row['ruleName'] ?? 'Wyjątek MG'))
                : ($names[$row['key']] ?? $row['key']);
            $before = (float) ($row['before'] ?? 0);
            $delta = (float) ($row['delta'] ?? 0);
            $row['amountChange'] = round($delta, 4);
            $row['percentChange'] = $before == 0.0 ? 0.0 : round(($delta / $before) * 100, 2);
        }
        unset($row);
        return $rows;
    }

    private function disabledModifiersFromRules(array $rules): array
    {
        $disabled = [];
        foreach ($rules as $rule) {
            foreach ((array) ($rule['effect']['disabledModifiers'] ?? []) as $key) {
                $disabled[$key][] = $rule['id'];
            }
        }
        return $disabled;
    }

    private function modifierStage(string $key): int
    {
        return [
            'worldProfile' => 3, 'shopType' => 4, 'location' => 5,
            'availability' => 6, 'demand' => 6, 'condition' => 6,
            'seasonality' => 7, 'marketEvents' => 7, 'wealth' => 8,
            'legality' => 9, 'counterfeitRisk' => 9, 'reputation' => 10,
        ][$key] ?? 10;
    }

    private function resolveDemandModifier(array $ctx): array
    {
        $level = $ctx['demandLevel'];
        $tables = [
            'buy' => ['very_low' => 0.86, 'low' => 0.93, 'normal' => 1, 'high' => 1.12, 'extreme' => 1.25],
            'sell' => ['very_low' => 0.74, 'low' => 0.88, 'normal' => 1, 'high' => 1.15, 'extreme' => 1.3],
        ];
        return $level === 'normal'
            ? $this->inactiveEffect($level, 'normal_demand')
            : $this->multiplierEffect($level, $tables[$ctx['mode']][$level], 'demand_' . $ctx['mode']);
    }

    private function resolveConditionModifier(array $ctx): array
    {
        $condition = $ctx['condition'];
        $tables = [
            'buy' => ['ruined' => 0.2, 'poor' => 0.45, 'worn' => 0.75, 'good' => 1, 'excellent' => 1.08],
            'sell' => ['ruined' => 0.1, 'poor' => 0.35, 'worn' => 0.65, 'good' => 1, 'excellent' => 1.05],
        ];
        return $condition === 'good'
            ? $this->inactiveEffect($condition, 'standard_condition')
            : $this->multiplierEffect($condition, $tables[$ctx['mode']][$condition], 'condition_' . $ctx['mode']);
    }
}
