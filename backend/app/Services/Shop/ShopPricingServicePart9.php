<?php

namespace App\Services\Shop;

trait ShopPricingServicePart9
{
    private function resolveMarketEventsModifier(array $ctx): array
    {
        $matched = [];
        $multiplier = 1.0;
        foreach (ShopProfileSchemaService::normalizeEvents($ctx['profile']['marketEvents'] ?? []) as $event) {
            if (!$this->marketEventMatches($event, $ctx)) continue;
            $matched[] = $event['name'] ?: $event['type'];
            $multiplier *= (float) $event['multiplier'];
        }
        if (!$matched) return $this->inactiveEffect('', 'no_active_market_event_for_item');
        $effect = $this->multiplierEffect(
            implode(', ', $matched),
            round($this->clamp($multiplier, 0.1, 5), 4),
            'active_market_events'
        );
        $effect['metadata'] = ['matchedEvents' => $matched];
        return $effect;
    }

    private function marketEventMatches(array $event, array $ctx): bool
    {
        if (!$event['enabled']) return false;
        if ($event['startsAt'] && $ctx['asOf'] < $event['startsAt']) return false;
        if ($event['endsAt'] && $ctx['asOf'] > $event['endsAt']) return false;
        if ($event['modes'] && !in_array($ctx['mode'], $event['modes'], true)) return false;
        $template = $ctx['template'];
        $templateId = (int) ($template['id'] ?? $template['ID'] ?? 0);
        $itemClass = strtoupper((string) ($template['item_class'] ?? $template['ITEM_CLASS'] ?? ''));
        $itemGenre = strtoupper((string) ($template['item_genre'] ?? $template['ITEM_GENRE'] ?? ''));
        $location = (string) ($ctx['profile']['locationType'] ?? '');
        return (
            (!$event['templateIds'] || in_array($templateId, $event['templateIds'], true)) &&
            (!$event['itemClasses'] || in_array($itemClass, $event['itemClasses'], true)) &&
            (!$event['itemGenres'] || in_array($itemGenre, $event['itemGenres'], true)) &&
            (!$event['locationTypes'] || in_array($location, $event['locationTypes'], true))
        );
    }

    private function resolveTemporaryModifier(array $ctx): array
    {
        $raw = $ctx['temporaryModifier'];
        $percent = is_array($raw) ? ($raw['percent'] ?? 0) : $raw;
        $percent = $this->number($percent, 0);
        if (abs($percent) < 0.0001) return $this->inactiveEffect('0', 'no_temporary_modifier');
        $limit = (float) ($ctx['pricingConfig']['guardrails']['maxTemporaryPercent'] ?? 100);
        $safe = $this->clamp($percent, -min(100, $limit), $limit);
        return $this->multiplierEffect($safe . '%', 1 + ($safe / 100), 'temporary_gm_modifier');
    }

    private function applyWealthBuybackLimit(
        float &$current,
        array &$breakdown,
        array $ctx,
        bool $bypass
    ): void {
        if ($bypass) return;
        $tier = (string) ($ctx['profile']['wealthTier'] ?? 'standard');
        $defaults = [
            'nedzny' => [120, 45], 'biedny' => [350, 140], 'standard' => [1800, 900],
            'bogaty' => [8000, 5000], 'elitarny' => [25000, 18000], 'luksusowy' => [80000, 60000],
        ];
        $settings = $ctx['profile']['marketSettings'];
        $budget = $settings['buybackBudget'] ?? ($defaults[$tier][0] ?? 1800);
        $itemLimit = $settings['maxBuybackItemValue'] ?? ($defaults[$tier][1] ?? 900);
        $batchLimit = (float) $budget / max(1, $ctx['quantityRequested']);
        $limit = max(0, min((float) $itemLimit, $batchLimit));
        $guarded = min($current, $limit);
        $this->appendFactor($current, $breakdown, 'wealthBuybackLimit', $this->fixedEffect(
            $tier . ':' . $limit,
            $guarded,
            $guarded < $current ? 'wealth_buyback_limit' : 'wealth_limit_not_reached'
        ), ['stage' => 14, 'input' => ['budget' => $budget, 'itemLimit' => $itemLimit]]);
    }

    private function availabilityChance(array $ctx): float
    {
        $chance = 62.0;
        $wealth = ['nedzny' => -22, 'biedny' => -12, 'standard' => 0, 'bogaty' => 10, 'elitarny' => 16, 'luksusowy' => 20];
        $enabled = (array) ($ctx['pricingConfig']['enabledModifiers'] ?? []);
        if (($enabled['wealth'] ?? true) !== false) {
            $chance += $wealth[$ctx['profile']['wealthTier'] ?? 'standard'] ?? 0;
        }
        $chance += (float) ($ctx['profile']['marketSettings']['availabilityBias'] ?? 0);
        if (($enabled['shopType'] ?? true) !== false) {
            $typeEffect = $this->resolveTypeModifier($ctx);
            $chance += (1 - (float) ($typeEffect['multiplier'] ?? 1)) * 100;
        }
        if (($enabled['worldProfile'] ?? true) !== false) {
            $worldEffect = $this->resolveWorldProfileModifier($ctx);
            $chance += ((float) ($worldEffect['multiplier'] ?? 1) - 1) * 100;
        }
        $settings = (array) ($ctx['profile']['marketSettings'] ?? []);
        $itemClass = strtolower((string) ($ctx['template']['item_class'] ?? $ctx['template']['ITEM_CLASS'] ?? ''));
        $itemGenre = strtolower((string) ($ctx['template']['item_genre'] ?? $ctx['template']['ITEM_GENRE'] ?? ''));
        $local = array_map('strtolower', (array) ($settings['localCategories'] ?? []));
        $imported = array_map('strtolower', (array) ($settings['importedCategories'] ?? []));
        if (($enabled['location'] ?? true) !== false &&
            (in_array($itemClass, $local, true) || in_array($itemGenre, $local, true))) {
            $chance += 14;
        } elseif (($enabled['location'] ?? true) !== false &&
            (in_array($itemClass, $imported, true) || in_array($itemGenre, $imported, true))) {
            $location = (string) ($ctx['profile']['locationType'] ?? 'miasto');
            $chance += in_array($location, ['port', 'port_morski', 'port_rzeczny'], true) ? 10 : -18;
        }
        if (($enabled['legality'] ?? true) !== false && $ctx['itemLegality'] === 'illegal') {
            $legal = (string) ($ctx['profile']['legalStatus'] ?? 'legal');
            $chance += in_array($legal, ['illegal', 'grey', 'mixed'], true) ? 12 : -45;
        }
        if (($enabled['marketEvents'] ?? true) !== false) {
            foreach (ShopProfileSchemaService::normalizeEvents($ctx['profile']['marketEvents'] ?? []) as $event) {
                if ($this->marketEventMatches($event, $ctx)) $chance += (float) $event['availabilityDelta'];
            }
        }
        $guard = $ctx['pricingConfig']['guardrails'];
        return round($this->clamp(
            $chance,
            (float) ($guard['minimumAvailabilityChance'] ?? 0),
            (float) ($guard['maximumAvailabilityChance'] ?? 100)
        ), 2);
    }
}
