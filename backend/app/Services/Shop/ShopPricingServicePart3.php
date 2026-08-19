<?php

namespace App\Services\Shop;

trait ShopPricingServicePart3
{
    public function calculateForTrade(
        array $template,
        array $profile = [],
        array $item = [],
        string $mode = 'buy'
    ): array {
        $ctx = $this->buildPricingContext($template, $profile, $item, $mode);
        $config = $ctx['pricingConfig'];
        $current = $ctx['sourceBasePrice'];
        $breakdown = [];
        $this->appendFactor($current, $breakdown, 'catalogPrice', $this->fixedEffect(
            (string) $ctx['sourceBasePrice'], $ctx['sourceBasePrice'], 'catalog_price'
        ), ['stage' => 1, 'input' => $ctx['sourceBasePrice']]);

        $this->appendFactor($current, $breakdown, 'currencyConversion', $this->multiplierEffect(
            $ctx['sourceCurrencyCode'] . '->' . $ctx['settlementCurrencyCode'],
            $ctx['currencyMultiplier'],
            $ctx['exchangeRateConfigured'] ? 'currency_conversion' : 'currency_rate_missing'
        ), [
            'stage' => 2,
            'input' => [
                'source' => $ctx['sourceCurrencyCode'],
                'target' => $ctx['settlementCurrencyCode'],
                'rate' => $ctx['exchangeRate'],
                'feePercent' => $ctx['exchangeFeePercent'],
            ],
            'exchangeRate' => $ctx['exchangeRate'],
            'exchangeRateConfigured' => $ctx['exchangeRateConfigured'],
        ]);
        $convertedPrice = $current;

        $baseKey = $ctx['mode'] === 'sell' ? 'buybackBase' : 'saleBase';
        $this->appendFactor($current, $breakdown, $baseKey, $this->multiplierEffect(
            $baseKey,
            (float) $config['baseMultipliers'][$ctx['mode']],
            $ctx['mode'] === 'sell' ? 'buyback_base' : 'sale_base'
        ), ['stage' => 3, 'input' => $config['baseMultipliers'][$ctx['mode']]]);
        $priceBeforeModifiers = $current;

        $matchedRules = $this->selectPricingRules($config, $ctx);
        $disabledByRules = $this->disabledModifiersFromRules($matchedRules);
        foreach ($this->modifierOrder() as $key) {
            $effect = $this->resolveModifier($key, $ctx);
            $globallyEnabled = ($config['enabledModifiers'][$key] ?? true) !== false;
            $suppressingRules = $disabledByRules[$key] ?? [];
            $this->appendFactor($current, $breakdown, $key, $effect, [
                'stage' => $this->modifierStage($key),
                'input' => $effect['source'] ?? '',
                'configurable' => true,
                'globallyEnabled' => $globallyEnabled,
                'suppressedByRules' => array_values($suppressingRules),
            ], $globallyEnabled && !$suppressingRules);
        }

        $this->appendFactor($current, $breakdown, 'pricingPolicy', $this->multiplierEffect(
            (string) ($config['policyId'] ?: 'general'), 1.0, 'pricing_policy_selected'
        ), ['stage' => 11, 'input' => $config['policyId'] ?: 'general']);

        $bypassGuardrails = false;
        foreach ($matchedRules as $rule) {
            $this->appendFactor(
                $current,
                $breakdown,
                'policyRule:' . $rule['id'],
                $this->effectForPricingRule($rule),
                [
                    'stage' => 12,
                    'input' => $rule['match'],
                    'ruleId' => $rule['id'],
                    'ruleName' => $rule['name'],
                    'priority' => $rule['priority'],
                ]
            );
            $bypassGuardrails = $bypassGuardrails || !empty($rule['effect']['ignoreGuardrails']);
        }

        $this->appendFactor($current, $breakdown, 'manualAdjustment',
            $this->resolveManualAdjustmentModifier($ctx),
            ['stage' => 13, 'input' => $ctx['entryAdjustmentPrice']]
        );
        $this->appendFactor($current, $breakdown, 'temporaryModifier',
            $this->resolveTemporaryModifier($ctx),
            ['stage' => 13, 'input' => $ctx['temporaryModifier']]
        );

        if (!is_finite($current) || $current < 0) {
            $this->appendFactor($current, $breakdown, 'finitePriceGuard', $this->fixedEffect(
                'invalid', 0, 'invalid_or_negative_price'
            ), ['stage' => 14, 'input' => $current]);
        }
        if (!empty($config['guardrails']['enabled']) && !$bypassGuardrails && $convertedPrice > 0) {
            $min = (float) $config['guardrails'][$ctx['mode'] . 'MinMultiplier'];
            $max = max($min, (float) $config['guardrails'][$ctx['mode'] . 'MaxMultiplier']);
            $guarded = $this->clamp($current, $convertedPrice * $min, $convertedPrice * $max);
            $this->appendFactor($current, $breakdown, 'guardrailRange', $this->fixedEffect(
                $min . '-' . $max,
                $guarded,
                abs($guarded - $current) >= 0.0001 ? 'guardrail_range' : 'guardrail_not_needed'
            ), ['stage' => 14, 'input' => [$min, $max]]);
        }
        if ($ctx['mode'] === 'sell') {
            $this->applyWealthBuybackLimit($current, $breakdown, $ctx, $bypassGuardrails);
        }

        $unrounded = round($current, 4);
        $step = max(1, (int) $config['roundingStep']);
        $rounded = $this->roundPrice($unrounded, $step, (string) $config['roundingMode']);
        $this->appendFactor($current, $breakdown, 'rounding', $this->fixedEffect(
            $config['roundingMode'] . ':' . $step,
            $rounded,
            abs($rounded - $current) >= 0.0001 ? 'rounding' : 'rounding_not_needed'
        ), ['stage' => 14, 'input' => ['mode' => $config['roundingMode'], 'step' => $step]]);
        $minimum = (int) $config['minimumPrice'];
        $this->appendFactor($current, $breakdown, 'minimumPrice', $this->fixedEffect(
            (string) $minimum,
            max($minimum, (int) round($current)),
            $current < $minimum ? 'minimum_price' : 'minimum_not_needed'
        ), ['stage' => 14, 'input' => $minimum]);
        $finalPrice = max(0, (int) round($current));

        if ($ctx['mode'] === 'sell' && !empty($config['guardrails']['enabled']) && !$bypassGuardrails) {
            $buyContext = $item;
            $buyContext['_skipBuybackReference'] = true;
            $referenceBuy = $this->calculateForTrade($template, $profile, $buyContext, 'buy');
            $cap = max(0, (int) floor(
                (float) $referenceBuy['finalPrice'] * (float) $config['guardrails']['maxBuybackRatio']
            ));
            if ($finalPrice > $cap) {
                $current = (float) $finalPrice;
                $this->appendFactor($current, $breakdown, 'guardrailBuybackRatio', $this->fixedEffect(
                    (string) $config['guardrails']['maxBuybackRatio'], $cap, 'guardrail_buyback_ratio'
                ), ['stage' => 14, 'input' => $cap, 'referenceBuyPrice' => $referenceBuy['finalPrice']]);
                $finalPrice = (int) $current;
            }
        }

        $breakdown = $this->finalizeBreakdown($breakdown);
        $available = $ctx['mode'] === 'buy' ? $ctx['stockQuantity'] : null;
        return [
            'mode' => $ctx['mode'],
            'policyId' => $config['policyId'],
            'appliedPolicyId' => $config['policyId'] ?: 'general',
            'catalogPrice' => $ctx['sourceBasePrice'],
            'basePrice' => $convertedPrice,
            'priceBeforeModifiers' => $priceBeforeModifiers,
            'sourceBasePrice' => $ctx['sourceBasePrice'],
            'sourceCurrencyCode' => $ctx['sourceCurrencyCode'],
            'settlementCurrencyCode' => $ctx['settlementCurrencyCode'],
            'exchangeRate' => $ctx['exchangeRate'],
            'exchangeRateConfigured' => $ctx['exchangeRateConfigured'],
            'exchangeFeePercent' => $ctx['exchangeFeePercent'],
            'unroundedPrice' => $unrounded,
            'finalPrice' => $finalPrice,
            'quantity' => $ctx['quantityRequested'],
            'totalPrice' => $finalPrice * $ctx['quantityRequested'],
            'availableQuantity' => $available,
            'availabilityChance' => $this->availabilityChance($ctx),
            'canTrade' => $available === null || $available >= $ctx['quantityRequested'],
            'priceTier' => $ctx['priceTier'],
            'itemLegality' => $ctx['itemLegality'],
            'condition' => $ctx['condition'],
            'demandLevel' => $ctx['demandLevel'],
            'reputation' => $ctx['actorReputation'],
            'counterfeit' => $ctx['counterfeit'],
            'matchedRuleIds' => array_column($matchedRules, 'id'),
            'guardrailsBypassed' => $bypassGuardrails,
            'modifiers' => $breakdown,
            'breakdown' => $breakdown,
            'activeModifiers' => array_values(array_filter($breakdown, static function (array $entry): bool {
                return !empty($entry['applied']) && abs((float) ($entry['amountChange'] ?? 0)) >= 0.0001;
            })),
        ];
    }
}
