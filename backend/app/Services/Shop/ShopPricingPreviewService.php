<?php

namespace App\Services\Shop;

use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopItemInstanceModel;
use App\Models\ShopTemplateModel;

class ShopPricingPreviewService
{
    private $profileService;
    private $pricingService;
    private $templateModel;
    private $containerModel;
    private $templateStockModel;
    private $instanceStockModel;
    private $instanceModel;

    public function __construct()
    {
        $this->profileService = new ShopProfileService();
        $this->pricingService = new ShopPricingService();
        $this->templateModel = new ShopTemplateModel();
        $this->containerModel = new ShopContainerModel();
        $this->templateStockModel = new ShopContainerTemplateItemModel();
        $this->instanceStockModel = new ShopContainerInstanceItemModel();
        $this->instanceModel = new ShopItemInstanceModel();
    }

    public function preview(int $campaignId, int $shopId, array $payload): ?array
    {
        $saved = $this->profileService->getProfile($campaignId, $shopId);
        if (!$saved) return null;
        $draft = $this->normalizeDraft($saved, (array) ($payload['draftProfile'] ?? []));
        $templateIds = $this->templateIds($payload);
        if (!$templateIds) return ['items' => [], 'beforeProfile' => $saved, 'afterProfile' => $draft];
        $templates = $this->templateModel
            ->where('campaign_id', $campaignId)
            ->where('deleted_at', null)
            ->whereIn('id', $templateIds)
            ->findAll();
        $stock = $this->stockByTemplate($campaignId, $shopId, $templateIds);
        $mode = strtolower((string) ($payload['mode'] ?? 'buy')) === 'sell' ? 'sell' : 'buy';
        $quantity = max(1, min(9999, (int) ($payload['quantity'] ?? 1)));
        $common = [
            'quantityRequested' => $quantity,
            'condition' => $payload['condition'] ?? 'good',
            'reputation' => $payload['reputation'] ?? null,
            'actorCode' => $payload['actorCode'] ?? null,
            'demandLevel' => $payload['demandLevel'] ?? null,
            'temporaryModifier' => $payload['temporaryModifier'] ?? 0,
            'asOf' => $payload['asOf'] ?? date('Y-m-d'),
            'manualPrice' => $payload['manualPrice'] ?? null,
        ];
        $items = [];
        foreach ($templates as $template) {
            $templateId = (int) $template['id'];
            $itemContext = array_merge($common, [
                'QUANTITY' => $stock[$templateId]['quantity'] ?? 0,
                'PRICE_OVERRIDE' => $stock[$templateId]['priceOverride'] ?? null,
            ]);
            if ($common['manualPrice'] === null) unset($itemContext['manualPrice']);
            if ($common['reputation'] === null) unset($itemContext['reputation']);
            if ($common['demandLevel'] === null) unset($itemContext['demandLevel']);
            $before = $this->pricingService->calculateForTrade($template, $saved, $itemContext, $mode);
            $after = $this->pricingService->calculateForTrade($template, $draft, $itemContext, $mode);
            $items[] = [
                'templateId' => $templateId,
                'templateName' => (string) ($template['name'] ?? ''),
                'itemClass' => (string) ($template['item_class'] ?? ''),
                'itemGenre' => (string) ($template['item_genre'] ?? ''),
                'stockQuantity' => $stock[$templateId]['displayQuantity'] ?? 0,
                'before' => $before,
                'after' => $after,
                'difference' => [
                    'amount' => $after['finalPrice'] - $before['finalPrice'],
                    'percent' => $before['finalPrice'] > 0
                        ? round((($after['finalPrice'] - $before['finalPrice']) / $before['finalPrice']) * 100, 2)
                        : 0,
                ],
            ];
        }
        return [
            'mode' => $mode,
            'quantity' => $quantity,
            'items' => $items,
            'hasUnsavedChanges' => $this->profileHash($saved) !== $this->profileHash($draft),
            'generatedAt' => date(DATE_ATOM),
        ];
    }

    private function normalizeDraft(array $saved, array $draft): array
    {
        $profile = array_merge($saved, $draft);
        $profile['pricingConfig'] = ShopPricingService::normalizePricingConfig(
            $draft['pricingConfig'] ?? $saved['pricingConfig'] ?? null
        );
        $profile['marketSettings'] = ShopProfileSchemaService::normalizeSettings(
            $draft['marketSettings'] ?? $saved['marketSettings'] ?? null
        );
        $profile['marketEvents'] = ShopProfileSchemaService::normalizeEvents(
            $draft['marketEvents'] ?? $saved['marketEvents'] ?? null
        );
        $profile['customPresets'] = ShopProfileSchemaService::normalizePresets(
            $draft['customPresets'] ?? $saved['customPresets'] ?? null
        );
        $profile['counterfeitRisk'] = max(0, min(100, (int) ($profile['counterfeitRisk'] ?? 10)));
        return $profile;
    }

    private function templateIds(array $payload): array
    {
        $raw = $payload['templateIds'] ?? [$payload['templateId'] ?? null];
        $ids = [];
        foreach (is_array($raw) ? array_slice($raw, 0, 12) : [] as $value) {
            $id = (int) $value;
            if ($id > 0) $ids[$id] = $id;
        }
        return array_values($ids);
    }

    private function stockByTemplate(int $campaignId, int $shopId, array $templateIds): array
    {
        $container = $this->containerModel
            ->where('campaign_id', $campaignId)
            ->where('shop_id', $shopId)
            ->where('container_type', 'SHOP')
            ->first();
        if (!$container) return [];
        $containerId = (int) $container['id'];
        $result = [];
        $rows = $this->templateStockModel
            ->where('campaign_id', $campaignId)
            ->where('container_id', $containerId)
            ->whereIn('template_id', $templateIds)
            ->findAll();
        foreach ($rows as $row) {
            $id = (int) $row['template_id'];
            $unlimited = $row['quantity'] === null;
            $result[$id] = [
                'quantity' => $unlimited ? 999999 : max(0, (int) $row['quantity']),
                'displayQuantity' => $unlimited ? null : max(0, (int) $row['quantity']),
                'priceOverride' => $row['price_override'],
            ];
        }
        $placements = $this->instanceStockModel
            ->where('campaign_id', $campaignId)
            ->where('container_id', $containerId)
            ->findAll();
        foreach ($placements as $placement) {
            $instance = $this->instanceModel->find((int) $placement['instance_id']);
            $id = (int) ($instance['template_id'] ?? 0);
            if (!$id || !in_array($id, $templateIds, true)) continue;
            $current = $result[$id]['quantity'] ?? 0;
            if ($current < 999999) {
                $result[$id]['quantity'] = $current + 1;
                $result[$id]['displayQuantity'] = ($result[$id]['displayQuantity'] ?? 0) + 1;
            }
            $result[$id]['priceOverride'] = $result[$id]['priceOverride'] ?? $placement['price_override'];
        }
        return $result;
    }

    private function profileHash(array $profile): string
    {
        unset($profile['updatedAt']);
        return md5((string) json_encode($profile, JSON_UNESCAPED_UNICODE));
    }
}
