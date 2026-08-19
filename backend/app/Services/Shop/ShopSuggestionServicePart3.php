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

trait ShopSuggestionServicePart3
{
    public function roll(int $campaignId, int $shopId, array $payload): array
    {
        $targetInstances = max(8, min(20, (int) ($payload['targetInstances'] ?? 12)));
        $dryRun = (bool) ($payload['dryRun'] ?? false);
        $clearExisting = array_key_exists('clearExisting', $payload) ? (bool) $payload['clearExisting'] : true;

        $generated = $this->generate($campaignId, $shopId);
        $pool = (array) ($generated['suggestions'] ?? []);

        if (!$pool) {
            return [
                'appliedUnique' => 0,
                'appliedInstances' => 0,
                'suggestions' => [],
            ];
        }

        usort($pool, function (array $a, array $b): int {
            return ((float) ($b['score'] ?? 0)) <=> ((float) ($a['score'] ?? 0));
        });

        $uniqueTarget = max(4, min(count($pool), (int) floor($targetInstances * 0.65)));
        $picked = array_slice($pool, 0, $uniqueTarget);

        $instances = 0;
        foreach ($picked as &$entry) {
            $entry['quantity'] = max(1, (int) ($entry['quantity'] ?? 1));
            $instances += $entry['quantity'];
        }
        unset($entry);

        while ($instances < $targetInstances && $picked) {
            $index = array_rand($picked);
            $picked[$index]['quantity'] = (int) $picked[$index]['quantity'] + 1;
            $instances++;
        }

        while ($instances > $targetInstances && $picked) {
            $index = array_rand($picked);
            if ((int) $picked[$index]['quantity'] > 1) {
                $picked[$index]['quantity'] = (int) $picked[$index]['quantity'] - 1;
                $instances--;
            } else {
                break;
            }
        }

        if ($dryRun) {
            return [
                'appliedUnique' => count($picked),
                'appliedInstances' => $instances,
                'suggestions' => array_values($picked),
            ];
        }

        $apply = $this->apply($campaignId, $shopId, [
            'suggestions' => $picked,
            'replaceExisting' => $clearExisting,
            'ownerCode' => strtoupper((string) ($payload['ownerCode'] ?? 'BG1')),
        ]);

        return [
            'ok' => (bool) ($apply['ok'] ?? false),
            'appliedUnique' => (int) ($apply['applied'] ?? 0),
            'appliedInstances' => (int) ($apply['appliedInstances'] ?? 0),
            'suggestions' => $picked,
            'containerState' => $apply['containerState'] ?? null,
        ];
    }

    private function saveCache(int $campaignId, int $shopId, array $suggestions, array $recommendations, string $profileHash): void
    {
        $existing = $this->cacheModel
            ->where('campaign_id', $campaignId)
            ->where('shop_id', $shopId)
            ->first();

        $payload = [
            'campaign_id' => $campaignId,
            'shop_id' => $shopId,
            'profile_hash' => $profileHash,
            'suggestions_json' => array_values($suggestions),
            'recommendations_json' => array_values($recommendations),
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->cacheModel->update((int) $existing['id'], $payload);
        } else {
            $this->cacheModel->insert($payload);
        }
    }

    private function buildRecommendations(array $templates, ?array $profile, ?array $typeMeta = null): array
    {
        $typeRule = $this->resolveTypeRule($profile, $typeMeta);
        $apiProfile = $profile ? (new ShopLegacyMapper())->profileToApi($profile) : [];
        $pricingEngine = new ShopPricingService();
        $typeReferences = [[
            'id' => (string) $typeRule['id'],
            'level' => 'type',
            'namePl' => (string) $typeRule['label'],
            'typicalLocations' => [],
            'suggestionRules' => [
                'requiredItemClasses' => (array) ($typeRule['primaryClasses'] ?? []),
                'preferredItemClasses' => (array) ($typeRule['secondaryClasses'] ?? []),
                'preferredGenres' => (array) ($typeRule['preferredGenres'] ?? []),
                'forbiddenTags' => [],
            ],
        ]];
        $worldReferences = [];
        try {
            $catalogService = new ShopCatalogService();
            $typeReferences = $catalogService->getCatalogNetwork() ?: $typeReferences;
            $worldReferences = $catalogService->getWorldProfiles();
        } catch (\Throwable $exception) {
            // Unit tests and pre-migration installs keep a deterministic type-only fallback.
        }
        $pricingEngine->setReferenceData($typeReferences, $worldReferences);
        $wealth = strtolower((string) ($profile['wealth_tier'] ?? 'standard'));
        $targets = [
            'nedzny' => 44,
            'biedny' => 90,
            'standard' => 210,
            'bogaty' => 620,
            'elitarny' => 1380,
            'luksusowy' => 2500,
        ];
        $target = $targets[$wealth] ?? 210;

        $recommendations = [];

        foreach ($templates as $template) {
            $price = max(1, (int) ($template['prize'] ?? 1));
            $distance = abs(log(($price + 1) / ($target + 1)));
            $priceScore = max(-50, 86 - ($distance * 34));
            $typeScore = $this->scoreTemplateForType($template, $typeRule);
            $marketPreview = $pricingEngine->calculateForTrade(
                $template,
                $apiProfile,
                ['QUANTITY' => 1, 'quantityRequested' => 1],
                'buy'
            );
            $availabilityScore = ((float) ($marketPreview['availabilityChance'] ?? 50) - 50) * 0.55;
            $scoreRaw = $priceScore + $typeScore + $availabilityScore;
            $tie = ((crc32((string) $template['id']) % 97) + 1) / 100;
            $score = round($scoreRaw + $tie, 2);

            $recommendation = $this->recommendationMeta($score);
            $itemClass = $this->templateItemClass($template);
            $itemGenre = $this->templateItemGenre($template);

            $suggestionId = 'template:'.((int) $template['id']);
            $variants = $this->buildPersonalizedVariants($suggestionId, $template);

            $recommendations[] = [
                'suggestionId' => $suggestionId,
                'templateId' => (int) $template['id'],
                'displayName' => (string) ($template['name'] ?? ''),
                'templateName' => (string) ($template['name'] ?? ''),
                'imgClass' => $this->itemIconResolver->resolve($template),
                'description' => (string) ($template['description'] ?? ''),
                'classKey' => $itemClass,
                'genreKey' => $itemGenre,
                'examples' => [],
                'label' => (string) ($template['name'] ?? ''),
                'reason' => ['Profil, cena, dostępność i rodzaj sklepu.'],
                'reasonDetails' => [
                    [
                        'textPl' => 'Skoring oparty o profil, cene i rodzaj sklepu.',
                        'refKey' => 'templateId',
                        'refValue' => (string) ((int) $template['id']),
                    ],
                    [
                        'textPl' => 'Dopasowanie do rodzaju sklepu: '.$typeRule['label'].'.',
                        'refKey' => 'shopType',
                        'refValue' => $typeRule['id'],
                    ],
                    [
                        'textPl' => 'Szansa dostępności: '.round((float) $marketPreview['availabilityChance']).'%.',
                        'refKey' => 'availabilityChance',
                        'refValue' => (string) ($marketPreview['availabilityChance'] ?? 0),
                    ],
                ],
                'personalizedVariants' => $variants,
                'scoreRaw' => round($scoreRaw, 4),
                'scoreTieBreaker' => round($tie, 4),
                'score' => $score,
                'availabilityChance' => (float) ($marketPreview['availabilityChance'] ?? 0),
                'suggestedPrice' => (int) ($marketPreview['finalPrice'] ?? $price),
                'priceTier' => (string) ($marketPreview['priceTier'] ?? 'mid'),
                'quantity' => 1,
                'recommendationCode' => $recommendation['code'],
                'recommendationLabelPl' => $recommendation['labelPl'],
                'recommendationReasonPl' => $recommendation['reasonPl'],
                'recommendationWeight' => $recommendation['weight'],
                'action' => 'use_existing',
                'segment' => $this->segmentForClass($itemClass),
            ];
        }

        $recommendations = array_merge(
            $recommendations,
            $this->buildDraftRecommendations($recommendations, $typeRule, $target)
        );
        usort($recommendations, static function (array $a, array $b): int {
            return ((float) ($b['score'] ?? 0)) <=> ((float) ($a['score'] ?? 0));
        });

        $settings = ShopProfileSchemaService::normalizeSettings($apiProfile['marketSettings'] ?? []);
        $defaultLimits = [
            'nedzny' => 0, 'biedny' => 1, 'standard' => 3,
            'bogaty' => 6, 'elitarny' => 10, 'luksusowy' => 14,
        ];
        $expensiveLimit = $settings['expensiveStockLimit'] === null
            ? ($defaultLimits[$wealth] ?? 3)
            : (int) $settings['expensiveStockLimit'];
        $expensiveSeen = 0;
        foreach ($recommendations as &$entry) {
            if (!in_array((string) ($entry['priceTier'] ?? ''), ['high', 'luxury'], true)) continue;
            $expensiveSeen++;
            if ($expensiveSeen <= $expensiveLimit) continue;
            $entry['recommendationWeight'] = 0;
            $entry['recommendationCode'] = 'skip';
            $entry['recommendationLabelPl'] = 'Poza limitem';
            $entry['recommendationReasonPl'] = 'Profil zamożności ogranicza liczbę drogich towarów.';
            $entry['reason'][] = 'Przekroczono limit drogich towarów: ' . $expensiveLimit . '.';
        }
        unset($entry);

        usort($recommendations, function (array $a, array $b): int {
            $weightDiff = ((int) ($b['recommendationWeight'] ?? 0)) <=> ((int) ($a['recommendationWeight'] ?? 0));
            if ($weightDiff !== 0) {
                return $weightDiff;
            }
            return ((float) ($b['score'] ?? 0)) <=> ((float) ($a['score'] ?? 0));
        });

        return $recommendations;
    }
}
