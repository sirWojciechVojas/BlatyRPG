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

trait ShopSuggestionServicePart4
{
    private function buildDraftRecommendations(array $existing, array $typeRule, int $target): array
    {
        $drafts = [];
        $usedNames = [];
        foreach ($existing as $entry) {
            $usedNames[strtolower((string) ($entry['templateName'] ?? ''))] = true;
        }

        $nextDraftId = 100000;
        foreach ((array) ($typeRule['seeds'] ?? []) as $seed) {
            $name = trim((string) ($seed['name'] ?? 'Nowy towar'));
            if (!$name || isset($usedNames[strtolower($name)])) {
                continue;
            }

            $itemClass = strtoupper((string) ($seed['class'] ?? 'TOOL'));
            $itemGenre = strtoupper((string) ($seed['genre'] ?? 'UTILITY'));
            $price = max(1, (int) ($seed['price'] ?? $target));
            $scoreRaw = $this->scoreDraftSeedForType($seed, $typeRule, $target);
            $tie = ((crc32((string) $typeRule['id'].':'.$nextDraftId) % 97) + 1) / 100;
            $score = round($scoreRaw + $tie, 2);
            $recommendation = $this->recommendationMeta($score);

            $draftTemplate = [
                'ID' => $nextDraftId,
                'NAME' => $name,
                'DESCRIPTION' => (string) ($seed['description'] ?? ''),
                'DETAILS' => 'AUTO_DRAFT',
                'ITEM_CLASS' => $itemClass,
                'ITEM_ID' => '',
                'ITEM_GENRE' => $itemGenre,
                'IMG_CLASS' => $this->itemIconResolver->resolve([
                    'NAME' => $name,
                    'DESCRIPTION' => (string) ($seed['description'] ?? ''),
                    'ITEM_CLASS' => $itemClass,
                    'ITEM_GENRE' => $itemGenre,
                ], (string) ($seed['imgClass'] ?? $this->defaultDraftIcon($itemClass))),
                'PRIZE' => $price,
                'CHARGE' => max(1, (int) ($seed['charge'] ?? 20)),
                'DRAFT' => true,
            ];

            $suggestionId = 'draft:'.$nextDraftId;
            $drafts[] = [
                'suggestionId' => $suggestionId,
                'draftTemplate' => $draftTemplate,
                'displayName' => $name,
                'templateName' => $name,
                'imgClass' => $draftTemplate['IMG_CLASS'],
                'description' => (string) ($seed['description'] ?? ''),
                'classKey' => $itemClass,
                'genreKey' => $itemGenre,
                'examples' => [],
                'label' => $name,
                'reason' => ['Brak gotowego szablonu, utworzono szkic towaru.'],
                'reasonDetails' => [
                    [
                        'textPl' => 'Auto draft oparty o rodzaj sklepu: '.$typeRule['label'].'.',
                        'refKey' => 'shopType',
                        'refValue' => $typeRule['id'],
                    ],
                ],
                'personalizedVariants' => $this->buildDraftVariants($suggestionId, $draftTemplate),
                'scoreRaw' => round($scoreRaw, 4),
                'scoreTieBreaker' => round($tie, 4),
                'score' => $score,
                'quantity' => max(1, (int) ($seed['quantity'] ?? 1)),
                'recommendationCode' => $recommendation['code'],
                'recommendationLabelPl' => $recommendation['labelPl'],
                'recommendationReasonPl' => 'Szkic towaru dopasowany do rodzaju sklepu.',
                'recommendationWeight' => $recommendation['weight'],
                'action' => 'create_draft',
                'segment' => (string) ($seed['segment'] ?? $this->segmentForClass($itemClass)),
            ];

            $nextDraftId++;
            if (count($drafts) >= 18) {
                break;
            }
        }

        return $drafts;
    }

    private function resolveShopTypeMeta(?array $profile): ?array
    {
        $typeId = $this->profileTypeId($profile);
        if (!$typeId) {
            return null;
        }

        $catalogNode = $this->catalogNodeModel
            ->where('node_key', $typeId)
            ->first();

        if ($catalogNode) {
            return [
                'id' => (string) ($catalogNode['node_key'] ?? $typeId),
                'name' => (string) ($catalogNode['name_pl'] ?? ''),
                'category' => '',
                'description' => (string) ($catalogNode['description_pl'] ?? ''),
            ];
        }

        $shopType = $this->shopTypeModel
            ->where('slug', $typeId)
            ->first();

        if ($shopType) {
            return [
                'id' => (string) ($shopType['slug'] ?? $typeId),
                'name' => (string) ($shopType['name'] ?? ''),
                'category' => (string) ($shopType['category'] ?? ''),
                'description' => (string) ($shopType['description'] ?? ''),
            ];
        }

        return [
            'id' => $typeId,
            'name' => $typeId,
            'category' => '',
            'description' => '',
        ];
    }

    private function resolveTypeRule(?array $profile, ?array $typeMeta = null): array
    {
        $typeId = $this->profileTypeId($profile);
        $keys = [$this->normalizeKey($typeId)];
        foreach ((array) ($profile['category_tags_json'] ?? $profile['categoryTags'] ?? []) as $tag) {
            $keys[] = $this->normalizeKey(str_replace('typ:', '', (string) $tag));
        }

        $sourceText = $this->normalizeText(implode(' ', array_filter([
            $typeId,
            $typeMeta['name'] ?? '',
            $typeMeta['category'] ?? '',
            $typeMeta['description'] ?? '',
            implode(' ', (array) ($profile['category_tags_json'] ?? $profile['categoryTags'] ?? [])),
        ])));

        foreach ($this->typeRuleCatalog() as $rule) {
            $aliases = array_map(function ($alias): string {
                return $this->normalizeKey((string) $alias);
            }, (array) ($rule['aliases'] ?? []));

            foreach ($keys as $key) {
                if ($key && in_array($key, $aliases, true)) {
                    return $rule;
                }
            }

            foreach ($aliases as $alias) {
                if ($alias && strpos($sourceText, $alias) !== false) {
                    return $rule;
                }
            }
        }

        return $this->typeRuleCatalog()['general'];
    }
}
