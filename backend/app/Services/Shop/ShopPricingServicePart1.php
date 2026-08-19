<?php

namespace App\Services\Shop;

trait ShopPricingServicePart1
{
    public function __construct()
    {
        $this->catalogService = new ShopCatalogService();
        $this->typeLookup = null;
        $this->worldLookup = null;
    }

    public function setReferenceData(array $catalogNodes = [], array $worldProfiles = []): self
    {
        $this->typeLookup = [];
        foreach ($catalogNodes as $node) {
            if (($node['level'] ?? '') === 'type') {
                $this->typeLookup[(string) ($node['id'] ?? '')] = $node;
            }
        }

        $this->worldLookup = [];
        foreach ($worldProfiles as $profile) {
            $this->worldLookup[(string) ($profile['id'] ?? '')] = $profile;
        }

        return $this;
    }

    public static function defaultPricingConfig(): array
    {
        return [
            'version' => 4,
            'policyId' => null,
            'baseMultipliers' => [
                'buy' => 1.0,
                'sell' => 0.6,
            ],
            'priceBands' => [
                'cheapMax' => 50.0,
                'midMax' => 200.0,
                'highMax' => 800.0,
            ],
            'currencyPolicy' => [
                'settlementCurrencyCode' => '',
                'exchangeRates' => [],
                'buyFeePercent' => 0.0,
                'sellFeePercent' => 0.0,
                'paymentExchangeFeePercent' => 5.0,
            ],
            'minimumPrice' => 1,
            'roundingStep' => 1,
            'roundingMode' => 'nearest',
            'guardrails' => [
                'enabled' => true,
                'buyMinMultiplier' => 0.25,
                'buyMaxMultiplier' => 4.0,
                'sellMinMultiplier' => 0.05,
                'sellMaxMultiplier' => 0.95,
                'maxBuybackRatio' => 0.9,
                'maxTemporaryPercent' => 100.0,
                'minimumAvailabilityChance' => 0.0,
                'maximumAvailabilityChance' => 100.0,
            ],
            'enabledModifiers' => [
                'shopType' => true,
                'availability' => true,
                'wealth' => true,
                'legality' => true,
                'reputation' => true,
                'location' => true,
                'seasonality' => true,
                'worldProfile' => true,
                'counterfeitRisk' => true,
                'demand' => true,
                'condition' => true,
                'marketEvents' => true,
            ],
            'rules' => [],
        ];
    }

    public static function normalizePricingConfig($input): array
    {
        $base = self::defaultPricingConfig();
        $raw = is_array($input) ? $input : [];
        $enabled = isset($raw['enabledModifiers']) && is_array($raw['enabledModifiers'])
            ? $raw['enabledModifiers']
            : [];
        $baseMultipliers = isset($raw['baseMultipliers']) && is_array($raw['baseMultipliers'])
            ? $raw['baseMultipliers']
            : [];
        $guardrails = isset($raw['guardrails']) && is_array($raw['guardrails'])
            ? $raw['guardrails']
            : [];
        $priceBands = isset($raw['priceBands']) && is_array($raw['priceBands'])
            ? $raw['priceBands']
            : [];
        $currencyPolicy = isset($raw['currencyPolicy']) && is_array($raw['currencyPolicy'])
            ? $raw['currencyPolicy']
            : [];

        foreach (array_keys($base['enabledModifiers']) as $key) {
            $base['enabledModifiers'][$key] = array_key_exists($key, $enabled)
                ? $enabled[$key] !== false
                : true;
        }

        $base['version'] = max(4, (int) ($raw['version'] ?? $base['version']));
        $base['baseMultipliers']['buy'] = self::clampStatic(
            self::numberStatic($baseMultipliers['buy'] ?? $base['baseMultipliers']['buy'], 1.0),
            0.0,
            10.0
        );
        $base['baseMultipliers']['sell'] = self::clampStatic(
            self::numberStatic($baseMultipliers['sell'] ?? $base['baseMultipliers']['sell'], 0.6),
            0.0,
            10.0
        );
        $base['priceBands']['cheapMax'] = max(0.0, self::numberStatic(
            $priceBands['cheapMax'] ?? $base['priceBands']['cheapMax'],
            $base['priceBands']['cheapMax']
        ));
        $base['priceBands']['midMax'] = max(
            $base['priceBands']['cheapMax'] + 1,
            self::numberStatic($priceBands['midMax'] ?? $base['priceBands']['midMax'], $base['priceBands']['midMax'])
        );
        $base['priceBands']['highMax'] = max(
            $base['priceBands']['midMax'] + 1,
            self::numberStatic($priceBands['highMax'] ?? $base['priceBands']['highMax'], $base['priceBands']['highMax'])
        );
        $base['currencyPolicy']['settlementCurrencyCode'] = substr(strtolower(trim(
            (string) ($currencyPolicy['settlementCurrencyCode'] ?? '')
        )), 0, 64);
        $base['currencyPolicy']['buyFeePercent'] = self::clampStatic(
            self::numberStatic($currencyPolicy['buyFeePercent'] ?? 0, 0),
            0.0,
            100.0
        );
        $base['currencyPolicy']['sellFeePercent'] = self::clampStatic(
            self::numberStatic($currencyPolicy['sellFeePercent'] ?? 0, 0),
            0.0,
            100.0
        );
        $base['currencyPolicy']['paymentExchangeFeePercent'] = self::clampStatic(
            self::numberStatic($currencyPolicy['paymentExchangeFeePercent'] ?? 5, 5),
            0.0,
            100.0
        );
        $exchangeRates = is_array($currencyPolicy['exchangeRates'] ?? null)
            ? $currencyPolicy['exchangeRates']
            : [];
        foreach ($exchangeRates as $currencyCode => $rate) {
            $code = substr(strtolower(trim((string) $currencyCode)), 0, 64);
            if ($code !== '') {
                $base['currencyPolicy']['exchangeRates'][$code] = self::clampStatic(
                    self::numberStatic($rate, 1.0),
                    0.000001,
                    1000000.0
                );
            }
        }
        $base['minimumPrice'] = max(0, (int) round((float) ($raw['minimumPrice'] ?? $base['minimumPrice'])));
        $base['roundingStep'] = max(1, (int) round((float) ($raw['roundingStep'] ?? $base['roundingStep'])));
        $base['roundingMode'] = in_array(($raw['roundingMode'] ?? ''), ['nearest', 'up', 'down'], true)
            ? (string) $raw['roundingMode']
            : 'nearest';
        $base['guardrails']['enabled'] = ($guardrails['enabled'] ?? true) !== false;
        foreach (['buyMinMultiplier', 'buyMaxMultiplier', 'sellMinMultiplier', 'sellMaxMultiplier'] as $key) {
            $base['guardrails'][$key] = self::clampStatic(
                self::numberStatic($guardrails[$key] ?? $base['guardrails'][$key], $base['guardrails'][$key]),
                0.0,
                10.0
            );
        }
        $base['guardrails']['maxBuybackRatio'] = self::clampStatic(
            self::numberStatic(
                $guardrails['maxBuybackRatio'] ?? $base['guardrails']['maxBuybackRatio'],
                $base['guardrails']['maxBuybackRatio']
            ),
            0.0,
            1.0
        );
        $base['guardrails']['maxTemporaryPercent'] = self::clampStatic(
            self::numberStatic($guardrails['maxTemporaryPercent'] ?? 100, 100),
            0.0,
            500.0
        );
        $base['guardrails']['minimumAvailabilityChance'] = self::clampStatic(
            self::numberStatic($guardrails['minimumAvailabilityChance'] ?? 0, 0),
            0.0,
            100.0
        );
        $base['guardrails']['maximumAvailabilityChance'] = self::clampStatic(
            self::numberStatic($guardrails['maximumAvailabilityChance'] ?? 100, 100),
            $base['guardrails']['minimumAvailabilityChance'],
            100.0
        );

        $usedIds = [];
        $rules = is_array($raw['rules'] ?? null) ? array_slice($raw['rules'], 0, 100) : [];
        foreach ($rules as $index => $rule) {
            $normalized = self::normalizePricingRule(is_array($rule) ? $rule : [], (int) $index);
            $id = $normalized['id'];
            $suffix = 2;
            while (isset($usedIds[$id])) {
                $id = $normalized['id'] . '-' . $suffix;
                $suffix++;
            }
            $normalized['id'] = $id;
            $usedIds[$id] = true;
            $base['rules'][] = $normalized;
        }

        return self::resolveAssignedPricingPolicy($base, $raw);
    }

    private static function generalPricingPolicyDefinition(): array
    {
        $default = self::defaultPricingConfig();

        return [
            'baseMultipliers' => $default['baseMultipliers'],
            'priceBands' => $default['priceBands'],
            'minimumPrice' => $default['minimumPrice'],
            'roundingStep' => $default['roundingStep'],
            'roundingMode' => $default['roundingMode'],
            'guardrails' => $default['guardrails'],
            'enabledModifiers' => $default['enabledModifiers'],
            'rules' => [],
        ];
    }

    private static function pricingPolicyDefinitions(): array
    {
        $general = self::generalPricingPolicyDefinition();
        $friendly = $general;
        $friendly['baseMultipliers'] = ['buy' => 0.9, 'sell' => 0.68];
        $friendly['guardrails'] = array_merge($general['guardrails'], [
            'buyMinMultiplier' => 0.2,
            'buyMaxMultiplier' => 3.0,
            'sellMaxMultiplier' => 0.9,
            'maxBuybackRatio' => 0.92,
            'enabled' => true,
        ]);
        $premium = $general;
        $premium['baseMultipliers'] = ['buy' => 1.2, 'sell' => 0.5];
        $premium['guardrails'] = array_merge($general['guardrails'], [
            'buyMinMultiplier' => 0.4,
            'buyMaxMultiplier' => 5.0,
            'sellMaxMultiplier' => 0.8,
            'maxBuybackRatio' => 0.82,
            'enabled' => true,
        ]);
        $unrestricted = $general;
        $unrestricted['guardrails']['enabled'] = false;

        return [
            'general' => $general,
            'balanced' => $general,
            'friendly' => $friendly,
            'premium' => $premium,
            'unrestricted' => $unrestricted,
        ];
    }

    private static function pricingPolicySnapshot(array $config): string
    {
        return (string) json_encode([
            'baseMultipliers' => $config['baseMultipliers'] ?? [],
            'priceBands' => $config['priceBands'] ?? [],
            'minimumPrice' => $config['minimumPrice'] ?? null,
            'roundingStep' => $config['roundingStep'] ?? null,
            'roundingMode' => $config['roundingMode'] ?? null,
            'guardrails' => $config['guardrails'] ?? [],
            'enabledModifiers' => $config['enabledModifiers'] ?? [],
            'rules' => $config['rules'] ?? [],
        ]);
    }
}
