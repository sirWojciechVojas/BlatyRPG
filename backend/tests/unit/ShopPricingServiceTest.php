<?php

use App\Services\Shop\ShopPricingService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ShopPricingServiceTest extends CIUnitTestCase
{
    public function testNormalizePricingConfigKeepsAllKnownFlags(): void
    {
        $config = ShopPricingService::normalizePricingConfig([
            'minimumPrice' => 0,
            'roundingStep' => 0,
            'enabledModifiers' => [
                'shopType' => false,
                'availability' => true,
            ],
        ]);

        $this->assertSame(0, $config['minimumPrice']);
        $this->assertSame(1, $config['roundingStep']);
        $this->assertFalse($config['enabledModifiers']['shopType']);
        $this->assertTrue($config['enabledModifiers']['availability']);
        $this->assertTrue($config['enabledModifiers']['wealth']);
        $this->assertSame('custom', $config['policyId']);
    }

    public function testMissingPolicyAssignmentUsesGeneralRules(): void
    {
        $config = ShopPricingService::normalizePricingConfig([
            'policyId' => null,
            'baseMultipliers' => ['buy' => 9, 'sell' => 9],
            'rules' => [
                [
                    'id' => 'stale-rule',
                    'effect' => ['type' => 'fixed', 'value' => 999],
                ],
            ],
            'currencyPolicy' => [
                'settlementCurrencyCode' => 'wfrp_empire',
                'exchangeRates' => ['wfrp_bretonnia' => 2],
            ],
        ]);

        $this->assertNull($config['policyId']);
        $this->assertSame(['buy' => 1.0, 'sell' => 0.6], $config['baseMultipliers']);
        $this->assertSame([], $config['rules']);
        $this->assertSame('wfrp_empire', $config['currencyPolicy']['settlementCurrencyCode']);
        $this->assertSame(2.0, $config['currencyPolicy']['exchangeRates']['wfrp_bretonnia']);
    }

    public function testNamedPolicyAssignmentIsAuthoritative(): void
    {
        $config = ShopPricingService::normalizePricingConfig([
            'policyId' => 'friendly',
            'baseMultipliers' => ['buy' => 9, 'sell' => 9],
            'guardrails' => ['maxBuybackRatio' => 0.1],
        ]);

        $this->assertSame('friendly', $config['policyId']);
        $this->assertSame(['buy' => 0.9, 'sell' => 0.68], $config['baseMultipliers']);
        $this->assertSame(0.92, $config['guardrails']['maxBuybackRatio']);
    }

    public function testCalculateForTradeRespectsMinimumPriceAndReturnsBreakdown(): void
    {
        $service = (new ShopPricingService())->setReferenceData(
            [
                [
                    'id' => 'armorer',
                    'level' => 'type',
                    'namePl' => 'Platnerz',
                    'typicalLocations' => ['miasto'],
                    'suggestionRules' => [
                        'requiredItemClasses' => ['ARMOR'],
                        'preferredItemClasses' => ['WEAPON'],
                        'preferredGenres' => [],
                        'forbiddenTags' => [],
                    ],
                ],
            ],
            [
                [
                    'id' => 'standard',
                    'labelPl' => 'Standard',
                    'modifiers' => [
                        'classBoosts' => ['WEAPON' => 2],
                        'genreBoosts' => [],
                        'legalityBias' => ['legal' => 2],
                        'priceTierBoosts' => ['cheap' => 0, 'mid' => 1, 'high' => 2, 'luxury' => 0],
                        'seasonalityBoosts' => [],
                    ],
                ],
            ]
        );

        $result = $service->calculateForTrade(
            [
                'name' => 'Noz kuchenny',
                'description' => 'Prosty noz.',
                'item_class' => 'WEAPON',
                'item_genre' => 'UTILITY',
                'prize' => 12,
            ],
            [
                'typeId' => 'armorer',
                'worldProfileId' => 'standard',
                'locationType' => 'miasto',
                'legalStatus' => 'legal',
                'wealthTier' => 'nedzny',
                'reputation' => 'fatalna',
                'seasonality' => 'caloroczny',
                'counterfeitRisk' => 100,
                'pricingConfig' => [
                    'minimumPrice' => 5,
                    'roundingStep' => 1,
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
                    ],
                ],
            ],
            [
                'QUANTITY' => 1,
                'PRICE_OVERRIDE' => null,
            ],
            'buy'
        );

        $this->assertSame('buy', $result['mode']);
        $this->assertSame(12.0, $result['basePrice']);
        $this->assertGreaterThanOrEqual(5, $result['finalPrice']);
        $this->assertNotEmpty($result['activeModifiers']);
        $this->assertContains('shopType', array_column($result['activeModifiers'], 'key'));
    }

    public function testMatchingExceptionCanSetFixedPriceAndStopLowerPriorityRules(): void
    {
        $service = (new ShopPricingService())->setReferenceData([], []);
        $disabled = array_fill_keys(
            array_keys(ShopPricingService::defaultPricingConfig()['enabledModifiers']),
            false
        );
        $result = $service->calculateForTrade(
            [
                'id' => 10,
                'item_class' => 'WEAPON',
                'item_genre' => 'UTILITY',
                'prize' => 120,
                'currency_code' => 'generic',
            ],
            [
                'pricingConfig' => [
                    'minimumPrice' => 0,
                    'guardrails' => ['enabled' => false],
                    'enabledModifiers' => $disabled,
                    'rules' => [
                        [
                            'id' => 'low',
                            'priority' => 10,
                            'match' => ['modes' => ['buy'], 'itemClasses' => ['WEAPON']],
                            'effect' => ['type' => 'fixed', 'value' => 5],
                        ],
                        [
                            'id' => 'high',
                            'priority' => 100,
                            'match' => ['modes' => ['buy'], 'templateIds' => [10]],
                            'effect' => [
                                'type' => 'fixed',
                                'value' => 77,
                                'stopProcessing' => true,
                            ],
                        ],
                    ],
                ],
            ],
            ['QUANTITY' => 2],
            'buy'
        );

        $this->assertSame(77, $result['finalPrice']);
        $this->assertSame(['high'], $result['matchedRuleIds']);
        $this->assertSame('policyRule:high', $result['activeModifiers'][0]['key']);
    }

    public function testBuybackCapIsAppliedAfterUpwardRounding(): void
    {
        $service = (new ShopPricingService())->setReferenceData([], []);
        $disabled = array_fill_keys(
            array_keys(ShopPricingService::defaultPricingConfig()['enabledModifiers']),
            false
        );
        $profile = [
            'pricingConfig' => [
                'roundingStep' => 100,
                'roundingMode' => 'up',
                'enabledModifiers' => $disabled,
            ],
        ];
        $template = [
            'id' => 10,
            'item_class' => 'MISC',
            'item_genre' => 'UTILITY',
            'prize' => 100,
            'currency_code' => 'generic',
        ];
        $item = ['QUANTITY' => 1];

        $buy = $service->calculateForTrade($template, $profile, $item, 'buy');
        $sell = $service->calculateForTrade($template, $profile, $item, 'sell');

        $this->assertSame(100, $buy['finalPrice']);
        $this->assertSame(90, $sell['finalPrice']);
        $this->assertLessThanOrEqual($buy['finalPrice'] * 0.9, $sell['finalPrice']);
        $this->assertSame(
            'guardrailBuybackRatio',
            $sell['activeModifiers'][count($sell['activeModifiers']) - 1]['key']
        );
    }

    public function testRegionalCurrencyIsConvertedBeforePriceRules(): void
    {
        $service = (new ShopPricingService())->setReferenceData([], []);
        $disabled = array_fill_keys(
            array_keys(ShopPricingService::defaultPricingConfig()['enabledModifiers']),
            false
        );
        $result = $service->calculateForTrade(
            [
                'id' => 10,
                'item_class' => 'MISC',
                'item_genre' => 'UTILITY',
                'prize' => 100,
                'currency_code' => 'wfrp_bretonnia',
            ],
            [
                'pricingConfig' => [
                    'minimumPrice' => 0,
                    'guardrails' => ['enabled' => false],
                    'enabledModifiers' => $disabled,
                    'currencyPolicy' => [
                        'settlementCurrencyCode' => 'wfrp_empire',
                        'exchangeRates' => ['wfrp_bretonnia' => 2],
                        'buyFeePercent' => 10,
                        'sellFeePercent' => 5,
                    ],
                ],
            ],
            ['QUANTITY' => 3],
            'buy'
        );

        $this->assertSame(100.0, $result['sourceBasePrice']);
        $this->assertEqualsWithDelta(220.0, $result['basePrice'], 0.0001);
        $this->assertSame(220, $result['finalPrice']);
        $this->assertSame('wfrp_empire', $result['settlementCurrencyCode']);
        $this->assertSame('currencyConversion', $result['activeModifiers'][0]['key']);
    }

    public function testLegacyGenericCurrencyUsesConcreteSettlementCurrencyWithoutRate(): void
    {
        $service = (new ShopPricingService())->setReferenceData([], []);
        $result = $service->calculateForTrade(
            [
                'id' => 10,
                'item_class' => 'MISC',
                'item_genre' => 'UTILITY',
                'prize' => 100,
                'currency_code' => 'generic',
            ],
            [
                'pricingConfig' => [
                    'currencyPolicy' => [
                        'settlementCurrencyCode' => 'wfrp_empire',
                        'exchangeRates' => [],
                    ],
                ],
            ],
            ['QUANTITY' => 1],
            'buy'
        );

        $this->assertTrue($result['exchangeRateConfigured']);
        $this->assertSame(1.0, $result['exchangeRate']);
        $this->assertSame('wfrp_empire', $result['sourceCurrencyCode']);
        $this->assertSame('wfrp_empire', $result['settlementCurrencyCode']);
    }

    public function testConcreteForeignCurrencyStillRequiresConfiguredRate(): void
    {
        $service = (new ShopPricingService())->setReferenceData([], []);
        $result = $service->calculateForTrade(
            [
                'id' => 10,
                'item_class' => 'MISC',
                'item_genre' => 'UTILITY',
                'prize' => 100,
                'currency_code' => 'wfrp_bretonnia',
            ],
            [
                'pricingConfig' => [
                    'currencyPolicy' => [
                        'settlementCurrencyCode' => 'wfrp_empire',
                        'exchangeRates' => [],
                    ],
                ],
            ],
            ['QUANTITY' => 1],
            'buy'
        );

        $this->assertFalse($result['exchangeRateConfigured']);
        $this->assertSame('wfrp_bretonnia', $result['sourceCurrencyCode']);
        $this->assertSame('wfrp_empire', $result['settlementCurrencyCode']);
    }

    public function testSeasonalityOnlyChangesMatchingCategories(): void
    {
        $service = (new ShopPricingService())->setReferenceData([], []);
        $profile = $this->isolatedProfile(['seasonality'], ['seasonality' => 'zima']);
        $cloth = $service->calculateForTrade($this->template('CLOTH'), $profile, ['QUANTITY' => 5], 'buy');
        $food = $service->calculateForTrade($this->template('FOOD'), $profile, ['QUANTITY' => 5], 'buy');

        $this->assertSame(108, $cloth['finalPrice']);
        $this->assertSame(100, $food['finalPrice']);
        $this->assertTrue($this->breakdownRow($cloth, 'seasonality')['applied']);
        $this->assertSame('season_not_relevant_for_category', $this->breakdownRow($food, 'seasonality')['skippedReason']);
    }

    public function testLegalityDoesNotMoveBreadButPricesIllegalRisk(): void
    {
        $service = (new ShopPricingService())->setReferenceData([], []);
        $profile = $this->isolatedProfile(['legality'], ['legalStatus' => 'legal']);
        $bread = $service->calculateForTrade($this->template('FOOD', 'legal'), $profile, [], 'buy');
        $poison = $service->calculateForTrade($this->template('ALCHEMY', 'illegal'), $profile, [], 'buy');

        $this->assertSame(100, $bread['finalPrice']);
        $this->assertSame(128, $poison['finalPrice']);
        $this->assertFalse($this->breakdownRow($bread, 'legality')['applied']);
    }

    public function testActorReputationChangesRetailAndBuybackInOppositeDirections(): void
    {
        $service = (new ShopPricingService())->setReferenceData([], []);
        $profile = $this->isolatedProfile(['reputation'], [
            'marketSettings' => ['reputationByActor' => ['BG1' => 'dobra']],
        ]);
        $template = $this->template('TOOL');
        $goodBuy = $service->calculateForTrade($template, $profile, ['actorCode' => 'BG1'], 'buy');
        $badBuy = $service->calculateForTrade($template, $profile, ['reputation' => 'fatalna'], 'buy');
        $goodSell = $service->calculateForTrade($template, $profile, ['reputation' => 'dobra'], 'sell');
        $badSell = $service->calculateForTrade($template, $profile, ['reputation' => 'fatalna'], 'sell');

        $this->assertLessThan($badBuy['finalPrice'], $goodBuy['finalPrice']);
        $this->assertGreaterThan($badSell['finalPrice'], $goodSell['finalPrice']);
    }

    public function testConditionAndDemandAreAppliedToTheValuedItem(): void
    {
        $service = (new ShopPricingService())->setReferenceData([], []);
        $profile = $this->isolatedProfile(['demand', 'condition']);
        $result = $service->calculateForTrade(
            $this->template('TOOL'),
            $profile,
            ['condition' => 'poor', 'demandLevel' => 'high'],
            'buy'
        );

        $this->assertSame(50, $result['finalPrice']);
        $this->assertSame(['demand', 'condition'], array_values(array_intersect(
            array_column($result['activeModifiers'], 'key'),
            ['demand', 'condition']
        )));
    }

    public function testDatedMarketEventHonoursItemScope(): void
    {
        $service = (new ShopPricingService())->setReferenceData([], []);
        $profile = $this->isolatedProfile(['marketEvents'], [
            'marketEvents' => [[
                'id' => 'siege-food', 'name' => 'Oblężenie', 'type' => 'siege', 'enabled' => true,
                'startsAt' => '2026-07-01', 'endsAt' => '2026-07-31', 'multiplier' => 1.25,
                'availabilityDelta' => -30, 'modes' => ['buy'], 'itemClasses' => ['FOOD'],
            ]],
        ]);
        $food = $service->calculateForTrade(
            $this->template('FOOD'), $profile, ['asOf' => '2026-07-16'], 'buy'
        );
        $weapon = $service->calculateForTrade(
            $this->template('WEAPON'), $profile, ['asOf' => '2026-07-16'], 'buy'
        );

        $this->assertSame(125, $food['finalPrice']);
        $this->assertSame(100, $weapon['finalPrice']);
        $this->assertLessThan($weapon['availabilityChance'], $food['availabilityChance']);
    }

    public function testLocalAndImportedGoodsAffectTransportAndAvailability(): void
    {
        $service = (new ShopPricingService())->setReferenceData([], []);
        $base = $this->isolatedProfile(['location'], ['locationType' => 'miasto']);
        $local = $base;
        $local['marketSettings'] = ['localCategories' => ['food']];
        $imported = $base;
        $imported['marketSettings'] = ['importedCategories' => ['food']];
        $template = $this->template('FOOD');
        $baseResult = $service->calculateForTrade($template, $base, [], 'buy');
        $localResult = $service->calculateForTrade($template, $local, [], 'buy');
        $importedResult = $service->calculateForTrade($template, $imported, [], 'buy');

        $this->assertLessThan($baseResult['finalPrice'], $localResult['finalPrice']);
        $this->assertGreaterThan($baseResult['finalPrice'], $importedResult['finalPrice']);
        $this->assertGreaterThan($baseResult['availabilityChance'], $localResult['availabilityChance']);
        $this->assertLessThan($baseResult['availabilityChance'], $importedResult['availabilityChance']);
    }

    public function testExplicitBuybackBudgetCapsWholeBatch(): void
    {
        $service = (new ShopPricingService())->setReferenceData([], []);
        $profile = $this->isolatedProfile([], [
            'marketSettings' => ['buybackBudget' => 90, 'maxBuybackItemValue' => 1000],
        ]);
        $result = $service->calculateForTrade(
            $this->template('MISC'), $profile, ['quantityRequested' => 3], 'sell'
        );

        $this->assertSame(30, $result['finalPrice']);
        $this->assertSame(90, $result['totalPrice']);
        $this->assertSame('wealth_buyback_limit', $this->breakdownRow($result, 'wealthBuybackLimit')['reason']);
    }

    public function testBreakdownContainsOrderedAuditableFields(): void
    {
        $result = (new ShopPricingService())->setReferenceData([], [])->calculateForTrade(
            $this->template('MISC'), $this->isolatedProfile([]), [], 'buy'
        );
        foreach ($result['breakdown'] as $index => $row) {
            $this->assertSame($index + 1, $row['order']);
            foreach (['name', 'source', 'operation', 'before', 'after', 'amountChange', 'percentChange', 'reason'] as $key) {
                $this->assertArrayHasKey($key, $row);
            }
        }
    }

    public function testCounterfeitRiskReturnsDeterministicProbabilityAndConsequence(): void
    {
        $service = (new ShopPricingService())->setReferenceData([], []);
        $profile = $this->isolatedProfile(['counterfeitRisk'], [
            'shopId' => 17, 'counterfeitRisk' => 100,
        ]);
        $template = $this->template('JEWELLERY');
        $template['counterfeit_sensitivity'] = 'high';
        $result = $service->calculateForTrade(
            $template, $profile, ['asOf' => '2026-07-16'], 'buy'
        );

        $this->assertSame(65.0, $result['counterfeit']['probabilityPercent']);
        $this->assertSame('inspection_reputation_or_legal_consequence', $result['counterfeit']['consequence']);
        $this->assertTrue($this->breakdownRow($result, 'counterfeitRisk')['applied']);
        $this->assertNotSame(100, $result['finalPrice']);
    }

    public function testInvalidAndNegativeInputsNeverProduceInvalidPrice(): void
    {
        $service = (new ShopPricingService())->setReferenceData([], []);
        foreach ([NAN, INF, -500] as $price) {
            $template = $this->template('MISC');
            $template['prize'] = $price;
            $result = $service->calculateForTrade(
                $template,
                $this->isolatedProfile([]),
                ['temporaryModifier' => INF],
                'buy'
            );
            $this->assertIsInt($result['finalPrice']);
            $this->assertGreaterThanOrEqual(0, $result['finalPrice']);
        }
    }

    private function isolatedProfile(array $enabled, array $overrides = []): array
    {
        $flags = array_fill_keys(
            array_keys(ShopPricingService::defaultPricingConfig()['enabledModifiers']),
            false
        );
        foreach ($enabled as $key) $flags[$key] = true;
        return array_replace_recursive([
            'pricingConfig' => [
                'policyId' => 'custom', 'minimumPrice' => 0, 'roundingStep' => 1,
                'guardrails' => ['enabled' => false], 'enabledModifiers' => $flags,
            ],
            'wealthTier' => 'standard', 'legalStatus' => 'legal', 'reputation' => 'neutralna',
            'seasonality' => 'caloroczny', 'counterfeitRisk' => 0, 'marketSettings' => [],
        ], $overrides);
    }

    private function template(string $class, string $legality = 'legal'): array
    {
        return [
            'id' => 701, 'name' => 'Towar testowy', 'item_class' => $class,
            'item_genre' => 'UTILITY', 'item_legality' => $legality,
            'prize' => 100, 'currency_code' => 'generic',
        ];
    }

    private function breakdownRow(array $result, string $key): array
    {
        foreach ($result['breakdown'] as $row) {
            if ($row['key'] === $key) return $row;
        }
        $this->fail('Missing breakdown row: ' . $key);
    }
}
