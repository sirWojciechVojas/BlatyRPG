<?php

namespace App\Services\Shop;

use App\Models\ShopCatalogNodeModel;
use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopItemInstanceModel;
use App\Models\ShopProfileModel;
use App\Models\ShopSuggestionCacheModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopTypeModel;

trait ShopSuggestionServicePart6
{
    private function scoreTemplateForType(array $template, array $typeRule): float
    {
        $itemClass = $this->templateItemClass($template);
        $itemGenre = $this->templateItemGenre($template);
        $score = 0.0;

        if (in_array($itemClass, (array) ($typeRule['primaryClasses'] ?? []), true)) {
            $score += 82;
        } elseif (in_array($itemClass, (array) ($typeRule['secondaryClasses'] ?? []), true)) {
            $score += 34;
        } else {
            $score -= 44;
        }

        if (in_array($itemGenre, (array) ($typeRule['preferredGenres'] ?? []), true)) {
            $score += 26;
        }

        $text = $this->normalizeText(implode(' ', [
            $template['name'] ?? $template['NAME'] ?? '',
            $template['description'] ?? $template['DESCRIPTION'] ?? '',
            $template['details'] ?? $template['DETAILS'] ?? '',
            $itemClass,
            $itemGenre,
        ]));

        $hits = 0;
        foreach ((array) ($typeRule['terms'] ?? []) as $term) {
            $term = $this->normalizeText((string) $term);
            if ($term && strpos($text, $term) !== false) {
                $hits++;
            }
        }

        $score += min(48, $hits * 12);
        return $score;
    }

    private function scoreDraftSeedForType(array $seed, array $typeRule, int $target): float
    {
        $price = max(1, (int) ($seed['price'] ?? $target));
        $distance = abs(log(($price + 1) / ($target + 1)));
        $template = [
            'name' => (string) ($seed['name'] ?? ''),
            'description' => (string) ($seed['description'] ?? ''),
            'item_class' => (string) ($seed['class'] ?? 'TOOL'),
            'item_genre' => (string) ($seed['genre'] ?? 'UTILITY'),
        ];

        return max(-35, 92 - ($distance * 28)) + $this->scoreTemplateForType($template, $typeRule);
    }

    private function recommendationMeta(float $score): array
    {
        if ($score >= 112) {
            return [
                'code' => 'add',
                'labelPl' => 'Dodaj',
                'reasonPl' => 'Wysokie dopasowanie do profilu sklepu.',
                'weight' => 3,
            ];
        }

        if ($score >= 54) {
            return [
                'code' => 'consider',
                'labelPl' => 'Rozwaz',
                'reasonPl' => 'Czesciowe dopasowanie.',
                'weight' => 2,
            ];
        }

        return [
            'code' => 'skip',
            'labelPl' => 'Pomin',
            'reasonPl' => 'Niskie dopasowanie.',
            'weight' => 1,
        ];
    }

    private function segmentForClass(string $itemClass): string
    {
        if (in_array($itemClass, ['TOOL', 'GADGET', 'STATIONERY'], true)) {
            return 'equipment';
        }
        if (in_array($itemClass, ['ALCHEMY', 'POTION', 'FOOD', 'FORAGE'], true)) {
            return 'ingredients';
        }
        return 'products';
    }

    private function defaultDraftIcon(string $itemClass): string
    {
        $icons = [
            'ALCHEMY' => 'v1074',
            'POTION' => 'v1074',
            'FOOD' => 'v0093',
            'CUTLERY' => 'v0739',
            'WEAPON' => 'v1289',
            'ARMOR' => 'v0328',
            'STATIONERY' => 'v0244',
            'JEWELLERY' => 'v0127',
            'GADGET' => 'v1042',
        ];

        return $icons[$itemClass] ?? 'v1058';
    }

    private function buildPersonalizedVariants(string $suggestionId, array $template): array
    {
        $baseName = (string) ($template['name'] ?? 'Przedmiot');
        $baseDesc = (string) ($template['description'] ?? '');
        $basePrice = (int) ($template['prize'] ?? 0);

        $variants = [];
        $labels = ['podstawowy', 'wzmocniony', 'podrozny'];
        foreach ($labels as $idx => $label) {
            $factor = [1.0, 1.15, 0.9][$idx];
            $variants[] = [
                'variantId' => $suggestionId.':v'.($idx + 1),
                'personalPseu' => $baseName.' '.$label,
                'personalDesc' => ($baseDesc ?: $baseName).'. Wersja '.$label.'.',
                'personalCost' => max(1, (int) round($basePrice * $factor)),
                'quantity' => 1,
            ];
        }

        return $variants;
    }

    private function buildDraftVariants(string $suggestionId, array $draftTemplate): array
    {
        $baseName = (string) ($draftTemplate['NAME'] ?? 'Przedmiot');
        $baseDesc = (string) ($draftTemplate['DESCRIPTION'] ?? '');
        $basePrice = (int) ($draftTemplate['PRIZE'] ?? 120);

        return [
            [
                'variantId' => $suggestionId.':v1',
                'personalPseu' => $baseName.' podstawowy',
                'personalDesc' => ($baseDesc ?: $baseName).'.',
                'personalCost' => $basePrice,
                'quantity' => 1,
            ],
            [
                'variantId' => $suggestionId.':v2',
                'personalPseu' => $baseName.' cechowy',
                'personalDesc' => ($baseDesc ?: $baseName).'. Wersja cechowa.',
                'personalCost' => (int) round($basePrice * 1.2),
                'quantity' => 1,
            ],
        ];
    }

    private function createTemplateFromDraft(int $campaignId, array $draft): ?array
    {
        if (!$draft) {
            return null;
        }

        $name = (string) ($draft['NAME'] ?? 'Szkic towaru');
        $itemClass = strtoupper((string) ($draft['ITEM_CLASS'] ?? 'TOOL'));
        $itemGenre = strtoupper((string) ($draft['ITEM_GENRE'] ?? 'UTILITY'));
        $existing = $this->templateModel
            ->where('campaign_id', $campaignId)
            ->where('deleted_at', null)
            ->where('draft', 1)
            ->where('name', $name)
            ->where('item_class', $itemClass)
            ->where('item_genre', $itemGenre)
            ->first();
        if ($existing) {
            $existing['_wasCreated'] = false;
            return $existing;
        }

        $record = [
            'campaign_id' => $campaignId,
            'name' => $name,
            'description' => (string) ($draft['DESCRIPTION'] ?? ''),
            'details' => (string) ($draft['DETAILS'] ?? ''),
            'item_class' => $itemClass,
            'item_id' => (string) ($draft['ITEM_ID'] ?? ''),
            'item_genre' => $itemGenre,
            'img_class' => $this->itemIconResolver->resolve($draft),
            'prize' => (int) ($draft['PRIZE'] ?? 0),
            'currency_code' => (string) (
                $draft['CURRENCY']
                ?? $this->currencyService
                    ->getCampaignCurrencyContext($campaignId)['defaultCurrencyCode']
                ?? 'generic'
            ),
            'charge' => (int) ($draft['CHARGE'] ?? 0),
            'draft' => 1,
            'weapon_json' => isset($draft['WEAPON']) ? (array) $draft['WEAPON'] : null,
        ];

        $this->templateModel->insert($record);

        $created = $this->templateModel->find((int) $this->templateModel->getInsertID());
        if ($created) {
            $created['_wasCreated'] = true;
        }
        return $created;
    }
}
