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

trait ShopSuggestionServicePart1
{
    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->cacheModel = new ShopSuggestionCacheModel();
        $this->templateModel = new ShopTemplateModel();
        $this->profileModel = new ShopProfileModel();
        $this->catalogNodeModel = new ShopCatalogNodeModel();
        $this->shopTypeModel = new ShopTypeModel();
        $this->containerTemplateItemModel = new ShopContainerTemplateItemModel();
        $this->containerInstanceItemModel = new ShopContainerInstanceItemModel();
        $this->itemInstanceModel = new ShopItemInstanceModel();
        $this->containerService = new ShopContainerService();
        $this->itemIconResolver = new ShopItemIconResolver();
        $this->currencyService = new ShopCurrencyService();
    }

    public function generate(int $campaignId, int $shopId): array
    {
        $profile = $this->profileModel
            ->where('campaign_id', $campaignId)
            ->where('shop_id', $shopId)
            ->first();

        $templates = $this->templateModel
            ->where('campaign_id', $campaignId)
            ->where('deleted_at', null)
            ->findAll();

        $typeMeta = $this->resolveShopTypeMeta($profile);
        $profileHash = md5(json_encode([$profile, $typeMeta, count($templates)], JSON_UNESCAPED_UNICODE));

        $recommendations = $this->buildRecommendations($templates, $profile, $typeMeta);
        $suggestions = array_slice($recommendations, 0, min(24, max(0, count($recommendations))));

        $payload = [
            'campaign_id' => $campaignId,
            'shop_id' => $shopId,
            'profile_hash' => $profileHash,
            'suggestions_json' => array_values($suggestions),
            'recommendations_json' => array_values($recommendations),
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        $existing = $this->cacheModel
            ->where('campaign_id', $campaignId)
            ->where('shop_id', $shopId)
            ->first();

        if ($existing) {
            $this->cacheModel->update((int) $existing['id'], $payload);
            $saved = $this->cacheModel->find((int) $existing['id']);
        } else {
            $this->cacheModel->insert($payload);
            $saved = $this->cacheModel->find((int) $this->cacheModel->getInsertID());
        }

        return [
            'suggestions' => (array) ($saved['suggestions_json'] ?? []),
            'recommendations' => (array) ($saved['recommendations_json'] ?? []),
            'profileHash' => (string) ($saved['profile_hash'] ?? ''),
            'generatedAt' => $saved['generated_at'] ?? null,
        ];
    }

    public function getCached(int $campaignId, int $shopId): array
    {
        $cache = $this->cacheModel
            ->where('campaign_id', $campaignId)
            ->where('shop_id', $shopId)
            ->first();

        if (!$cache) {
            return [
                'suggestions' => [],
                'recommendations' => [],
                'profileHash' => '',
                'generatedAt' => null,
            ];
        }

        return [
            'suggestions' => (array) ($cache['suggestions_json'] ?? []),
            'recommendations' => (array) ($cache['recommendations_json'] ?? []),
            'profileHash' => (string) ($cache['profile_hash'] ?? ''),
            'generatedAt' => $cache['generated_at'] ?? null,
        ];
    }

    public function promote(int $campaignId, int $shopId, int $count = 30): array
    {
        $count = max(1, min(120, $count));
        $cached = $this->getCached($campaignId, $shopId);
        $suggestions = (array) $cached['suggestions'];
        $recommendations = (array) $cached['recommendations'];

        $existingIds = [];
        foreach ($suggestions as $entry) {
            $existingIds[(string) ($entry['suggestionId'] ?? '')] = true;
        }

        $added = 0;
        foreach ($recommendations as $entry) {
            $id = (string) ($entry['suggestionId'] ?? '');
            if (!$id || isset($existingIds[$id])) {
                continue;
            }
            $existingIds[$id] = true;
            $suggestions[] = $entry;
            $added++;
            if ($added >= $count) {
                break;
            }
        }

        $this->saveCache($campaignId, $shopId, $suggestions, $recommendations, (string) $cached['profileHash']);

        return [
            'added' => $added,
            'suggestions' => $suggestions,
        ];
    }
}
