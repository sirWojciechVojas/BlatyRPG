<?php

namespace App\Services\Shop;

trait ShopPricingServicePart5
{
    private function resolveSeasonalityModifier(array $ctx): array
    {
        $seasonality = (string) ($ctx['profile']['seasonality'] ?? 'caloroczny');
        $itemClass = strtoupper((string) ($ctx['template']['item_class'] ?? $ctx['template']['ITEM_CLASS'] ?? ''));
        $itemGenre = strtoupper((string) ($ctx['template']['item_genre'] ?? $ctx['template']['ITEM_GENRE'] ?? ''));

        $buy = [
            'caloroczny' => null,
            'sezonowy' => in_array($itemClass, ['FOOD', 'CLOTH'], true) ? 1.06 : null,
            'wiosna' => in_array($itemClass, ['FOOD', 'ALCHEMY'], true) ? 0.98 : null,
            'lato' => in_array($itemGenre, ['FOOD', 'DRINKS'], true) ? 0.96 : null,
            'jesien' => in_array($itemClass, ['FOOD', 'TOOL'], true) ? 1.02 : null,
            'zima' => in_array($itemClass, ['CLOTH', 'ALCHEMY', 'TOOL'], true) ? 1.08 : null,
            'zniwa' => in_array($itemClass, ['TOOL', 'FOOD'], true) ? 0.94 : null,
            'jarmark' => in_array($itemClass, ['FOOD', 'CLOTH', 'TOOL', 'JEWELLERY'], true) ? 0.94 : null,
            'swieta' => in_array($itemClass, ['JEWELLERY', 'STATIONERY', 'FOOD'], true) ? 1.12 : null,
        ];

        $buyMultiplier = $buy[$seasonality] ?? null;
        if ($buyMultiplier === null) {
            return $this->inactiveEffect(
                $seasonality . ':' . ($itemClass ?: ($itemGenre ?: 'generic')),
                'season_not_relevant_for_category'
            );
        }
        $sellMultiplier = round(1 + (($buyMultiplier - 1) * 0.6), 4);

        return $this->multiplierEffect(
            $seasonality . ':' . ($itemClass ?: ($itemGenre ?: 'generic')),
            $ctx['mode'] === 'sell' ? $sellMultiplier : $buyMultiplier,
            'seasonality_' . $ctx['mode']
        );
    }

    private function resolveWorldProfileModifier(array $ctx): array
    {
        $world = $ctx['worldProfile'];
        if (!$world || !is_array($world['modifiers'] ?? null)) {
            return $this->inactiveEffect('', 'no_world_profile');
        }

        $modifiers = (array) $world['modifiers'];
        $itemClass = strtoupper((string) ($ctx['template']['item_class'] ?? $ctx['template']['ITEM_CLASS'] ?? ''));
        $itemGenre = strtoupper((string) ($ctx['template']['item_genre'] ?? $ctx['template']['ITEM_GENRE'] ?? ''));
        $priceTier = (string) $ctx['priceTier'];
        $seasonality = (string) ($ctx['profile']['seasonality'] ?? 'caloroczny');
        $itemLegality = (string) $ctx['itemLegality'];

        $classBoost = $this->number($modifiers['classBoosts'][$itemClass] ?? 0);
        $genreBoost = $this->number($modifiers['genreBoosts'][$itemGenre] ?? 0);
        $priceTierBoost = $this->number($modifiers['priceTierBoosts'][$priceTier] ?? 0);
        $seasonalityBoost = $this->number($modifiers['seasonalityBoosts'][$seasonality] ?? 0);
        $legalityBoost = $this->number($modifiers['legalityBias'][$ctx['profile']['legalStatus'] ?? ''] ?? 0);
        $totalBoost = $classBoost + $genreBoost + $priceTierBoost + $seasonalityBoost + ($itemLegality === 'illegal' ? $legalityBoost : $legalityBoost / 2);
        $divisor = $ctx['mode'] === 'sell' ? 160 : 100;
        $multiplier = $this->clamp(1 + ($totalBoost / $divisor), 0.8, 1.3);

        return $this->multiplierEffect(
            (string) (($world['labelPl'] ?? $world['id'] ?? 'world') . ':' . round($totalBoost)),
            round($multiplier, 4),
            'world_profile_' . $ctx['mode']
        );
    }

    private function resolveCounterfeitRiskModifier(array $ctx): array
    {
        $risk = $this->clamp($this->number($ctx['profile']['counterfeitRisk'] ?? 10), 0, 100);
        $sensitivity = (string) $ctx['counterfeitSensitivity'];
        $legalStatus = (string) ($ctx['profile']['legalStatus'] ?? 'legal');
        $shady = in_array($legalStatus, ['grey', 'illegal', 'mixed'], true);
        $weights = ['low' => 0.18, 'medium' => 0.42, 'high' => 0.65];
        $weight = $weights[$sensitivity] ?? 0.18;

        $counterfeit = (array) ($ctx['counterfeit'] ?? []);
        if (!empty($counterfeit['suspected'])) {
            $effect = $this->multiplierEffect(
                $risk . ':' . $sensitivity . ':suspected',
                $ctx['mode'] === 'buy' ? 0.72 : 0.55,
                'deterministic_counterfeit_suspected'
            );
            $effect['metadata'] = $counterfeit;
            return $effect;
        }

        if ($risk <= 0 || $sensitivity === 'low') {
            return $this->inactiveEffect($risk . ':' . $sensitivity, 'counterfeit_risk_not_material');
        }

        if ($ctx['mode'] === 'sell') {
            $delta = $shady
                ? 1 + (($risk / 100) * $weight * 0.12)
                : 1 - (($risk / 100) * $weight * 0.24);
            $effect = $this->multiplierEffect(
                $risk . ':' . $sensitivity,
                round($this->clamp($delta, 0.78, 1.08), 4),
                'counterfeit_sell'
            );
            $effect['metadata'] = $counterfeit;
            return $effect;
        }

        $delta = $shady
            ? 1 + (($risk / 100) * $weight * 0.14)
            : 1 - (($risk / 100) * $weight * 0.20);

        $effect = $this->multiplierEffect(
            $risk . ':' . $sensitivity,
            round($this->clamp($delta, 0.82, 1.12), 4),
            'counterfeit_buy'
        );
        $effect['metadata'] = $counterfeit;
        return $effect;
    }

    private function resolveManualAdjustmentModifier(array $ctx): array
    {
        $override = $ctx['entryAdjustmentPrice'];
        if ($override === null || $override === '') {
            return $this->inactiveEffect('', 'no_manual_adjustment');
        }

        $overridePrice = max(0, $this->number($override));
        return $this->fixedEffect((string) $overridePrice, $overridePrice, 'manual_adjustment_fixed');
    }

    private static function normalizePricingRule(array $input, int $index): array
    {
        $match = is_array($input['match'] ?? null) ? $input['match'] : [];
        $effect = is_array($input['effect'] ?? null) ? $input['effect'] : [];
        $allowedEffectTypes = ['multiplier', 'additive', 'fixed'];
        $effectType = in_array(($effect['type'] ?? ''), $allowedEffectTypes, true)
            ? (string) $effect['type']
            : 'multiplier';
        $defaultValue = $effectType === 'multiplier' ? 1.0 : 0.0;
        $rawValue = self::numberStatic($effect['value'] ?? $defaultValue, $defaultValue);
        $effectValue = $effectType === 'multiplier'
            ? self::clampStatic($rawValue, 0.0, 10.0)
            : self::clampStatic($rawValue, -1000000.0, 1000000.0);
        $modes = self::normalizeLowerList($match['modes'] ?? ($match['mode'] ?? []), ['buy', 'sell']);
        if (!$modes) {
            $modes = ['buy', 'sell'];
        }

        $rawId = preg_replace('/[^a-zA-Z0-9_-]/', '-', trim((string) ($input['id'] ?? '')));
        $rawId = preg_replace('/-+/', '-', (string) $rawId);
        $id = substr((string) $rawId, 0, 80);
        if ($id === '') {
            $id = 'pricing-rule-' . ($index + 1);
        }

        $templateIds = [];
        foreach (self::arrayInput($match['templateIds'] ?? []) as $templateId) {
            $value = (int) round(self::numberStatic($templateId, 0));
            if ($value > 0) {
                $templateIds[$value] = $value;
            }
        }

        $modifierLookup = [];
        foreach (array_keys(self::defaultPricingConfig()['enabledModifiers']) as $key) {
            $modifierLookup[strtolower($key)] = $key;
        }
        $disabledModifiers = [];
        foreach (self::arrayInput($effect['disabledModifiers'] ?? []) as $key) {
            $normalizedKey = $modifierLookup[strtolower(trim((string) $key))] ?? null;
            if ($normalizedKey !== null) {
                $disabledModifiers[$normalizedKey] = $normalizedKey;
            }
        }

        return [
            'id' => $id,
            'name' => substr(trim((string) ($input['name'] ?? '')), 0, 120),
            'enabled' => ($input['enabled'] ?? true) !== false,
            'priority' => (int) round(self::clampStatic(
                self::numberStatic($input['priority'] ?? 0, 0),
                -1000,
                1000
            )),
            'match' => [
                'modes' => $modes,
                'templateIds' => array_slice(array_values($templateIds), 0, 100),
                'itemClasses' => array_slice(self::normalizeUpperListStatic($match['itemClasses'] ?? []), 0, 50),
                'itemGenres' => array_slice(self::normalizeUpperListStatic($match['itemGenres'] ?? []), 0, 50),
                'currencyCodes' => array_slice(self::normalizeFreeLowerList($match['currencyCodes'] ?? []), 0, 50),
                'priceTiers' => self::normalizeLowerList($match['priceTiers'] ?? [], ['cheap', 'mid', 'high', 'luxury']),
                'legalities' => self::normalizeLowerList($match['legalities'] ?? [], ['legal', 'grey', 'illegal']),
                'availabilityBands' => self::normalizeLowerList(
                    $match['availabilityBands'] ?? [],
                    ['none', 'scarce', 'low', 'medium', 'high']
                ),
            ],
            'effect' => [
                'type' => $effectType,
                'value' => $effectValue,
                'disabledModifiers' => array_values($disabledModifiers),
                'stopProcessing' => ($effect['stopProcessing'] ?? false) === true,
                'ignoreGuardrails' => ($effect['ignoreGuardrails'] ?? false) === true,
            ],
        ];
    }

    private function selectPricingRules(array $pricingConfig, array $ctx): array
    {
        $entries = [];
        foreach ((array) ($pricingConfig['rules'] ?? []) as $index => $rule) {
            $entries[] = ['rule' => $rule, 'index' => $index];
        }
        usort($entries, static function (array $left, array $right): int {
            $priority = ((int) $right['rule']['priority']) <=> ((int) $left['rule']['priority']);
            return $priority !== 0 ? $priority : ((int) $left['index'] <=> (int) $right['index']);
        });

        $selected = [];
        foreach ($entries as $entry) {
            $rule = $entry['rule'];
            if (!$this->pricingRuleMatches($rule, $ctx)) {
                continue;
            }
            $selected[] = $rule;
            if (!empty($rule['effect']['stopProcessing'])) {
                break;
            }
        }
        return $selected;
    }

    private function pricingRuleMatches(array $rule, array $ctx): bool
    {
        if (empty($rule['enabled'])) {
            return false;
        }
        $match = (array) ($rule['match'] ?? []);
        $template = (array) $ctx['template'];
        $templateId = (int) ($template['id'] ?? $template['ID'] ?? 0);
        $itemClass = strtoupper((string) ($template['item_class'] ?? $template['ITEM_CLASS'] ?? $template['itemClass'] ?? ''));
        $itemGenre = strtoupper((string) ($template['item_genre'] ?? $template['ITEM_GENRE'] ?? $template['itemGenre'] ?? ''));
        $currencyCode = strtolower((string) ($template['currency_code'] ?? $template['CURRENCY'] ?? $template['currencyCode'] ?? 'generic'));
        $availabilityBand = $this->resolveAvailabilityBand((int) $ctx['stockQuantity']);

        return (
            (!$match['modes'] || in_array($ctx['mode'], $match['modes'], true)) &&
            (!$match['templateIds'] || in_array($templateId, $match['templateIds'], true)) &&
            (!$match['itemClasses'] || in_array($itemClass, $match['itemClasses'], true)) &&
            (!$match['itemGenres'] || in_array($itemGenre, $match['itemGenres'], true)) &&
            (!$match['currencyCodes'] || in_array($currencyCode, $match['currencyCodes'], true)) &&
            (!$match['priceTiers'] || in_array($ctx['priceTier'], $match['priceTiers'], true)) &&
            (!$match['legalities'] || in_array($ctx['itemLegality'], $match['legalities'], true)) &&
            (!$match['availabilityBands'] || in_array($availabilityBand, $match['availabilityBands'], true))
        );
    }

    private function effectForPricingRule(array $rule): array
    {
        $source = (string) (($rule['name'] ?? '') ?: $rule['id']);
        $type = (string) ($rule['effect']['type'] ?? 'multiplier');
        $value = $this->number($rule['effect']['value'] ?? ($type === 'multiplier' ? 1 : 0));
        if ($type === 'fixed') {
            return $this->fixedEffect($source, $value, 'policy_rule_fixed');
        }
        if ($type === 'additive') {
            return $this->additiveEffect($source, $value, 'policy_rule_additive');
        }
        return $this->multiplierEffect($source, $value, 'policy_rule_multiplier');
    }
}
