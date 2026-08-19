<?php

namespace App\Services\Shop;

trait ShopPricingServicePart4
{
    private function modifierOrder(): array
    {
        return [
            'worldProfile',
            'shopType',
            'location',
            'availability',
            'demand',
            'condition',
            'seasonality',
            'marketEvents',
            'wealth',
            'legality',
            'counterfeitRisk',
            'reputation',
        ];
    }

    private function resolveModifier(string $key, array $ctx): array
    {
        switch ($key) {
            case 'shopType':
                return $this->resolveTypeModifier($ctx);
            case 'availability':
                return $this->resolveAvailabilityModifier($ctx);
            case 'demand':
                return $this->resolveDemandModifier($ctx);
            case 'condition':
                return $this->resolveConditionModifier($ctx);
            case 'wealth':
                return $this->resolveWealthModifier($ctx);
            case 'legality':
                return $this->resolveLegalityModifier($ctx);
            case 'reputation':
                return $this->resolveReputationModifier($ctx);
            case 'location':
                return $this->resolveLocationModifier($ctx);
            case 'seasonality':
                return $this->resolveSeasonalityModifier($ctx);
            case 'marketEvents':
                return $this->resolveMarketEventsModifier($ctx);
            case 'worldProfile':
                return $this->resolveWorldProfileModifier($ctx);
            case 'counterfeitRisk':
                return $this->resolveCounterfeitRiskModifier($ctx);
            default:
                return $this->inactiveEffect();
        }
    }

    private function resolveTypeModifier(array $ctx): array
    {
        $typeNode = $ctx['typeNode'];
        if (!$typeNode) {
            return $this->inactiveEffect('', 'no_type');
        }

        $rules = (array) ($typeNode['suggestionRules'] ?? []);
        $requiredClasses = array_flip($this->normalizeUpperList($rules['requiredItemClasses'] ?? []));
        $preferredClasses = array_flip($this->normalizeUpperList($rules['preferredItemClasses'] ?? []));
        $preferredGenres = array_flip($this->normalizeUpperList($rules['preferredGenres'] ?? []));
        $forbiddenTags = array_map([$this, 'normalizeText'], (array) ($rules['forbiddenTags'] ?? []));
        $itemClass = strtoupper((string) ($ctx['template']['item_class'] ?? $ctx['template']['ITEM_CLASS'] ?? ''));
        $itemGenre = strtoupper((string) ($ctx['template']['item_genre'] ?? $ctx['template']['ITEM_GENRE'] ?? ''));
        $templateText = $this->templateText($ctx['template']);

        $matchLevel = 'off_specialization';
        if ($forbiddenTags && $this->hasToken($templateText, $forbiddenTags)) {
            $matchLevel = 'forbidden';
        } elseif (isset($requiredClasses[$itemClass])) {
            $matchLevel = 'core';
        } elseif (isset($preferredClasses[$itemClass]) || isset($preferredGenres[$itemGenre])) {
            $matchLevel = 'preferred';
        }

        if ($ctx['mode'] === 'sell') {
            $multipliers = [
                'forbidden' => 0.74,
                'off_specialization' => 0.88,
                'preferred' => 1.04,
                'core' => 1.10,
            ];
            return $this->multiplierEffect(
                (string) (($typeNode['namePl'] ?? $typeNode['id'] ?? 'type') . ':' . $matchLevel),
                $multipliers[$matchLevel] ?? 1,
                'shop_type_sell'
            );
        }

        $multipliers = [
            'forbidden' => 1.24,
            'off_specialization' => 1.12,
            'preferred' => 0.97,
            'core' => 0.92,
        ];
        return $this->multiplierEffect(
            (string) (($typeNode['namePl'] ?? $typeNode['id'] ?? 'type') . ':' . $matchLevel),
            $multipliers[$matchLevel] ?? 1,
            'shop_type_buy'
        );
    }

    private function resolveAvailabilityModifier(array $ctx): array
    {
        $quantity = max(0, (int) $ctx['stockQuantity']);
        $band = $this->resolveAvailabilityBand($quantity);

        if ($ctx['mode'] === 'sell') {
            $multipliers = [
                'none' => 1.12,
                'scarce' => 1.08,
                'low' => 1.04,
                'medium' => 0.98,
                'high' => 0.90,
            ];
            return $this->multiplierEffect($band . ':' . $quantity, $multipliers[$band] ?? 1, 'availability_sell');
        }

        $multipliers = [
            'none' => 1.34,
            'scarce' => 1.26,
            'low' => 1.16,
            'medium' => 1.06,
            'high' => 0.96,
        ];
        return $this->multiplierEffect($band . ':' . $quantity, $multipliers[$band] ?? 1, 'availability_buy');
    }

    private function resolveWealthModifier(array $ctx): array
    {
        $tier = (string) ($ctx['profile']['wealthTier'] ?? 'standard');
        $priceTier = (string) $ctx['priceTier'];

        $buy = [
            'nedzny' => ['cheap' => 0.95, 'mid' => 1.02, 'high' => 1.14, 'luxury' => 1.24],
            'biedny' => ['cheap' => 0.97, 'mid' => 1.01, 'high' => 1.10, 'luxury' => 1.18],
            'standard' => ['cheap' => 1.00, 'mid' => 1.00, 'high' => 1.00, 'luxury' => 1.00],
            'bogaty' => ['cheap' => 1.02, 'mid' => 1.01, 'high' => 0.96, 'luxury' => 0.92],
            'elitarny' => ['cheap' => 1.04, 'mid' => 1.02, 'high' => 0.94, 'luxury' => 0.88],
            'luksusowy' => ['cheap' => 1.08, 'mid' => 1.04, 'high' => 0.92, 'luxury' => 0.84],
        ];
        $sell = [
            'nedzny' => ['cheap' => 0.94, 'mid' => 0.90, 'high' => 0.82, 'luxury' => 0.72],
            'biedny' => ['cheap' => 0.97, 'mid' => 0.94, 'high' => 0.86, 'luxury' => 0.78],
            'standard' => ['cheap' => 1.00, 'mid' => 1.00, 'high' => 1.00, 'luxury' => 1.00],
            'bogaty' => ['cheap' => 1.01, 'mid' => 1.04, 'high' => 1.10, 'luxury' => 1.16],
            'elitarny' => ['cheap' => 1.02, 'mid' => 1.06, 'high' => 1.14, 'luxury' => 1.22],
            'luksusowy' => ['cheap' => 1.04, 'mid' => 1.08, 'high' => 1.18, 'luxury' => 1.28],
        ];

        $table = $ctx['mode'] === 'sell' ? $sell : $buy;
        return $this->multiplierEffect($tier . ':' . $priceTier, $table[$tier][$priceTier] ?? 1, 'wealth_' . $ctx['mode']);
    }

    private function resolveLegalityModifier(array $ctx): array
    {
        $shopLegality = (string) ($ctx['profile']['legalStatus'] ?? 'legal');
        $itemLegality = (string) $ctx['itemLegality'];

        $buy = [
            'legal' => ['legal' => 1.00, 'grey' => 1.08, 'illegal' => 1.28],
            'licensed' => ['legal' => 0.99, 'grey' => 1.04, 'illegal' => 1.22],
            'grey' => ['legal' => 0.98, 'grey' => 1.01, 'illegal' => 1.14],
            'illegal' => ['legal' => 1.02, 'grey' => 1.08, 'illegal' => 1.18],
            'mixed' => ['legal' => 1.00, 'grey' => 1.03, 'illegal' => 1.12],
        ];
        $sell = [
            'legal' => ['legal' => 1.00, 'grey' => 0.90, 'illegal' => 0.76],
            'licensed' => ['legal' => 1.01, 'grey' => 0.94, 'illegal' => 0.80],
            'grey' => ['legal' => 0.98, 'grey' => 1.00, 'illegal' => 1.08],
            'illegal' => ['legal' => 0.94, 'grey' => 1.02, 'illegal' => 1.16],
            'mixed' => ['legal' => 0.99, 'grey' => 1.00, 'illegal' => 1.08],
        ];

        $table = $ctx['mode'] === 'sell' ? $sell : $buy;
        $multiplier = $table[$shopLegality][$itemLegality] ?? 1;
        if ($itemLegality === 'legal' && abs($multiplier - 1) < 0.015) {
            return $this->inactiveEffect($shopLegality . ':' . $itemLegality, 'ordinary_legal_good_unaffected');
        }
        return $this->multiplierEffect($shopLegality . ':' . $itemLegality, $multiplier, 'legality_' . $ctx['mode']);
    }

    private function resolveReputationModifier(array $ctx): array
    {
        $reputation = (string) ($ctx['actorReputation'] ?? 'neutralna');
        $buy = [
            'fatalna' => 1.18,
            'zla' => 1.10,
            'podejrzana' => 1.00,
            'neutralna' => 1.00,
            'dobra' => 0.95,
            'znakomita' => 0.89,
        ];
        $sell = [
            'fatalna' => 0.82,
            'zla' => 0.90,
            'podejrzana' => 0.96,
            'neutralna' => 1.00,
            'dobra' => 1.06,
            'znakomita' => 1.12,
        ];

        $table = $ctx['mode'] === 'sell' ? $sell : $buy;
        return $this->multiplierEffect($reputation, $table[$reputation] ?? 1, 'reputation_' . $ctx['mode']);
    }

    private function resolveLocationModifier(array $ctx): array
    {
        $location = (string) ($ctx['profile']['locationType'] ?? 'miasto');
        $aliases = [
            'rynek' => 'miasto',
            'dzielnica_kupiecka' => 'strefa_cechowa',
            'dzielnica_rzemieslnicza' => 'strefa_cechowa',
            'przedmiescie' => 'obrzeza',
            'trakt' => 'przy_trakcie',
            'zamek' => 'forteca',
            'klasztor' => 'strefa_swiatynna',
        ];
        $locationKey = $aliases[$location] ?? $location;
        $priceTier = (string) $ctx['priceTier'];
        $typicalLocations = array_map('strval', (array) ($ctx['typeNode']['typicalLocations'] ?? []));
        $isTypical = in_array($location, $typicalLocations, true);

        $buy = [
            'metropolia' => ['cheap' => 1.04, 'mid' => 1.06, 'high' => 1.10, 'luxury' => 1.14],
            'miasto' => ['cheap' => 1.02, 'mid' => 1.03, 'high' => 1.06, 'luxury' => 1.08],
            'miasteczko' => ['cheap' => 1.00, 'mid' => 1.00, 'high' => 1.02, 'luxury' => 1.06],
            'wies' => ['cheap' => 0.96, 'mid' => 0.98, 'high' => 1.08, 'luxury' => 1.18],
            'jarmark' => ['cheap' => 0.98, 'mid' => 1.00, 'high' => 1.04, 'luxury' => 1.08],
            'port' => ['cheap' => 1.01, 'mid' => 1.03, 'high' => 1.07, 'luxury' => 1.10],
            'port_morski' => ['cheap' => 1.02, 'mid' => 1.05, 'high' => 1.10, 'luxury' => 1.12],
            'port_rzeczny' => ['cheap' => 1.01, 'mid' => 1.03, 'high' => 1.08, 'luxury' => 1.10],
            'forteca' => ['cheap' => 1.03, 'mid' => 1.06, 'high' => 1.08, 'luxury' => 1.10],
            'przy_trakcie' => ['cheap' => 1.01, 'mid' => 1.03, 'high' => 1.06, 'luxury' => 1.08],
            'obrzeza' => ['cheap' => 0.98, 'mid' => 1.00, 'high' => 1.06, 'luxury' => 1.12],
            'dzielnica_bogata' => ['cheap' => 1.05, 'mid' => 1.08, 'high' => 1.12, 'luxury' => 1.16],
            'dzielnica_biedna' => ['cheap' => 0.95, 'mid' => 0.98, 'high' => 1.08, 'luxury' => 1.14],
            'strefa_swiatynna' => ['cheap' => 1.00, 'mid' => 1.02, 'high' => 1.06, 'luxury' => 1.08],
            'strefa_cechowa' => ['cheap' => 1.01, 'mid' => 1.02, 'high' => 1.04, 'luxury' => 1.06],
        ];
        $sell = [
            'metropolia' => ['cheap' => 1.02, 'mid' => 1.03, 'high' => 1.08, 'luxury' => 1.12],
            'miasto' => ['cheap' => 1.01, 'mid' => 1.02, 'high' => 1.05, 'luxury' => 1.08],
            'miasteczko' => ['cheap' => 1.00, 'mid' => 1.00, 'high' => 1.02, 'luxury' => 1.04],
            'wies' => ['cheap' => 0.94, 'mid' => 0.96, 'high' => 1.04, 'luxury' => 1.10],
            'jarmark' => ['cheap' => 0.98, 'mid' => 1.01, 'high' => 1.04, 'luxury' => 1.06],
            'port' => ['cheap' => 1.00, 'mid' => 1.02, 'high' => 1.05, 'luxury' => 1.08],
            'port_morski' => ['cheap' => 1.01, 'mid' => 1.03, 'high' => 1.08, 'luxury' => 1.10],
            'port_rzeczny' => ['cheap' => 1.00, 'mid' => 1.02, 'high' => 1.06, 'luxury' => 1.08],
            'forteca' => ['cheap' => 1.01, 'mid' => 1.03, 'high' => 1.06, 'luxury' => 1.08],
            'przy_trakcie' => ['cheap' => 1.00, 'mid' => 1.02, 'high' => 1.04, 'luxury' => 1.06],
            'obrzeza' => ['cheap' => 0.97, 'mid' => 0.99, 'high' => 1.04, 'luxury' => 1.08],
            'dzielnica_bogata' => ['cheap' => 1.03, 'mid' => 1.05, 'high' => 1.10, 'luxury' => 1.14],
            'dzielnica_biedna' => ['cheap' => 0.94, 'mid' => 0.96, 'high' => 1.02, 'luxury' => 1.06],
            'strefa_swiatynna' => ['cheap' => 0.99, 'mid' => 1.01, 'high' => 1.04, 'luxury' => 1.06],
            'strefa_cechowa' => ['cheap' => 1.01, 'mid' => 1.02, 'high' => 1.04, 'luxury' => 1.06],
        ];

        $table = $ctx['mode'] === 'sell' ? $sell : $buy;
        $locationMultiplier = $table[$locationKey][$priceTier] ?? 1;
        $fitMultiplier = $isTypical ? 0.99 : 1.05;
        $settings = (array) ($ctx['profile']['marketSettings'] ?? []);
        $itemClass = strtolower((string) ($ctx['template']['item_class'] ?? $ctx['template']['ITEM_CLASS'] ?? ''));
        $itemGenre = strtolower((string) ($ctx['template']['item_genre'] ?? $ctx['template']['ITEM_GENRE'] ?? ''));
        $local = array_map('strtolower', (array) ($settings['localCategories'] ?? []));
        $imported = array_map('strtolower', (array) ($settings['importedCategories'] ?? []));
        $isLocal = in_array($itemClass, $local, true) || in_array($itemGenre, $local, true);
        $isImported = in_array($itemClass, $imported, true) || in_array($itemGenre, $imported, true);
        $transportMultiplier = 1.0;
        $transportKind = 'standard';
        if ($isLocal) {
            $transportMultiplier = $ctx['mode'] === 'sell' ? 0.96 : 0.94;
            $transportKind = 'local';
        } elseif ($isImported) {
            $isPort = in_array($locationKey, ['port', 'port_morski', 'port_rzeczny'], true);
            $isRemote = in_array($locationKey, ['wies', 'obrzeza', 'forteca'], true);
            $transportMultiplier = $isPort
                ? ($ctx['mode'] === 'sell' ? 1.02 : 0.96)
                : ($isRemote
                    ? ($ctx['mode'] === 'sell' ? 1.10 : 1.18)
                    : ($ctx['mode'] === 'sell' ? 1.06 : 1.10));
            $transportKind = $isPort ? 'imported_port' : ($isRemote ? 'imported_remote' : 'imported');
        }

        return $this->multiplierEffect(
            $location . ':' . ($isTypical ? 'typical' : 'atypical') . ':' . $transportKind,
            round($locationMultiplier * $fitMultiplier * $transportMultiplier, 4),
            'location_' . $ctx['mode']
        );
    }
}
