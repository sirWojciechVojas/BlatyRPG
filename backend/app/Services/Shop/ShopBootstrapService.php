<?php

namespace App\Services\Shop;

use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopItemInstanceModel;
use App\Models\ShopModel;
use App\Models\ShopTemplateModel;

class ShopBootstrapService
{
    private $shopModel;
    private $templateModel;
    private $containerTemplateItemModel;
    private $containerInstanceItemModel;
    private $instanceModel;
    private $walletService;
    private $contextBuilder;
    private $containerInitializer;
    private $profileService;
    private $suggestionService;
    private $catalogService;
    private $currencyService;
    private $mechanicsService;
    private $mapper;

    public function __construct()
    {
        $this->shopModel = new ShopModel();
        $this->templateModel = new ShopTemplateModel();
        $this->containerTemplateItemModel = new ShopContainerTemplateItemModel();
        $this->containerInstanceItemModel = new ShopContainerInstanceItemModel();
        $this->instanceModel = new ShopItemInstanceModel();
        $this->walletService = new ShopWalletService();
        $this->contextBuilder = new ShopBootstrapContextBuilder();
        $this->containerInitializer = new ShopContainerInitializer();
        $this->profileService = new ShopProfileService();
        $this->suggestionService = new ShopSuggestionService();
        $this->catalogService = new ShopCatalogService();
        $this->currencyService = new ShopCurrencyService();
        $this->mechanicsService = new ShopMechanicsService();
        $this->mapper = new ShopLegacyMapper();
    }

    public function build(int $campaignId, string $ownerCode = 'BG1', array $authContext = []): array
    {
        $ownerCode = strtoupper($ownerCode ?: 'BG1');
        $currencyContext = $this->currencyService->getCampaignCurrencyContext($campaignId);

        $shopsRaw = $this->shopModel
            ->where('campaign_id', $campaignId)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->findAll();
        $containerMap = $this->containerInitializer->ensureAndMap($campaignId, $ownerCode, $shopsRaw);

        $templatesRaw = $this->templateModel
            ->where('campaign_id', $campaignId)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->findAll();

        $templateById = [];
        foreach ($templatesRaw as $template) {
            $templateById[(int) $template['id']] = $template;
        }

        $templateRows = $this->containerTemplateItemModel
            ->where('campaign_id', $campaignId)
            ->findAll();

        $instancePlacements = $this->containerInstanceItemModel
            ->where('campaign_id', $campaignId)
            ->findAll();

        $instanceById = [];
        $instanceRows = $this->instanceModel
            ->where('campaign_id', $campaignId)
            ->findAll();
        foreach ($instanceRows as $instance) {
            $instanceById[(int) $instance['id']] = $instance;
        }

        $access = $this->contextBuilder->access($campaignId, $authContext);
        $isGm = !empty($access['permissions']['isGm']);
        $managementView = $isGm && empty($authContext['character_view']);
        $visibleContainers = array_values(array_filter(
            (array) ($containerMap['ALL'] ?? []),
            static function (array $container) use ($managementView, $ownerCode): bool {
                if ($managementView) {
                    return true;
                }
                $type = strtoupper((string) ($container['container_type'] ?? ''));
                if ($type === 'SHOP') {
                    return true;
                }
                return in_array($type, ['CHARACTER', 'TRASH'], true)
                    && strtoupper((string) ($container['owner_code'] ?? '')) === $ownerCode;
            }
        ));
        $visibleContainerIds = array_map(static fn (array $container): int => (int) $container['id'], $visibleContainers);
        $visibleTemplateRows = array_values(array_filter(
            $templateRows,
            static fn (array $row): bool => in_array((int) $row['container_id'], $visibleContainerIds, true)
        ));
        $visibleInstancePlacements = array_values(array_filter(
            $instancePlacements,
            static fn (array $row): bool => in_array((int) $row['container_id'], $visibleContainerIds, true)
        ));
        $visibleInstanceIds = array_map(static fn (array $row): int => (int) $row['instance_id'], $visibleInstancePlacements);
        $visibleInstanceRows = array_values(array_filter(
            $instanceRows,
            static fn (array $row): bool => in_array((int) $row['id'], $visibleInstanceIds, true)
        ));

        $placementsByContainerId = [];
        foreach ($instancePlacements as $placement) {
            $placementsByContainerId[(int) $placement['container_id']][] = $placement;
        }
        $templateRowsByContainerId = [];
        foreach ($templateRows as $row) {
            $templateRowsByContainerId[(int) $row['container_id']][] = $row;
        }
        $containerState = [
            'containers' => $visibleContainers,
            'templateRows' => $visibleTemplateRows,
            'instanceRows' => $visibleInstancePlacements,
            'itemInstances' => $visibleInstanceRows,
        ];

        $shops = [];
        foreach ($shopsRaw as $shopRaw) {
            $shop = $this->mapper->shopToLegacy($shopRaw);
            $shopContainerId = (int) ($containerMap['SHOP_BY_ID'][(int) $shopRaw['id']] ?? 0);

            if ($shopContainerId) {
                foreach ($templateRowsByContainerId[$shopContainerId] ?? [] as $row) {
                    $template = $templateById[(int) $row['template_id']] ?? null;
                    if (!$template) {
                        continue;
                    }
                    $entry = $this->mapper->shopEntryFromTemplateRow($row, $template);
                    $shop['shopEntries'][] = $entry;
                    $shop['items'][] = $entry;
                    $shop['itemIds'][] = (int) $row['template_id'];
                }
                foreach ($placementsByContainerId[$shopContainerId] ?? [] as $placement) {
                    $instance = $instanceById[(int) $placement['instance_id']] ?? null;
                    if (!$instance) {
                        continue;
                    }
                    $template = $templateById[(int) $instance['template_id']] ?? null;
                    if (!$template) {
                        continue;
                    }

                    $entry = $this->mapper->inventoryFromInstanceRow(
                        $placement,
                        $instance,
                        $template,
                        'DEFAULT',
                        $ownerCode,
                        'STOISKO'
                    );
                    $shop['shopEntries'][] = $entry;
                    $shop['items'][] = $entry;
                    $shop['itemIds'][] = (int) $instance['template_id'];
                }
            }

            $shop['itemIds'] = array_values(array_unique(array_map('intval', $shop['itemIds'])));
            $shops[] = $shop;
        }

        $activeShop = null;
        foreach ($shops as $shop) {
            if (!empty($shop['isActive'])) {
                $activeShop = $shop;
                break;
            }
        }
        if (!$activeShop && $shops) {
            $activeShop = $shops[0];
        }

        $inventoryItems = [];
        $trashItems = [];

        $characterContainerId = (int) ($containerMap['CHARACTER'] ?? 0);
        $defaultContainerId = (int) ($containerMap['DEFAULT'] ?? 0);
        $ownerTrashContainerId = (int) ($containerMap['OWNER_TRASH'] ?? 0);
        $systemTrashContainerId = (int) ($containerMap['TRASH'] ?? 0);

        foreach ($templateRows as $row) {
            $template = $templateById[(int) $row['template_id']] ?? null;
            if (!$template) {
                continue;
            }

            $containerId = (int) $row['container_id'];
            if ($containerId === $characterContainerId) {
                $inventoryItems[] = $this->mapper->inventoryFromTemplateRow($row, $template, $ownerCode, $ownerCode, 'PLECY');
            } elseif ($managementView && $containerId === $defaultContainerId) {
                $inventoryItems[] = $this->mapper->inventoryFromTemplateRow($row, $template, 'DEFAULT', $ownerCode, 'DEFAULT');
            } elseif ($containerId === $ownerTrashContainerId || ($managementView && $containerId === $systemTrashContainerId)) {
                $trashItems[] = $this->mapper->inventoryFromTemplateRow(
                    $row,
                    $template,
                    'TRASH',
                    $ownerCode,
                    'STOS',
                    'TEMPLATE',
                    (int) $row['template_id']
                );
            }
        }

        foreach ($instancePlacements as $placement) {
            $instance = $instanceById[(int) $placement['instance_id']] ?? null;
            if (!$instance) {
                continue;
            }
            $template = $templateById[(int) $instance['template_id']] ?? null;
            if (!$template) {
                continue;
            }

            $containerId = (int) $placement['container_id'];
            if ($containerId === $characterContainerId) {
                $inventoryItems[] = $this->mapper->inventoryFromInstanceRow($placement, $instance, $template, $ownerCode, $ownerCode, 'PLECY');
            } elseif ($managementView && $containerId === $defaultContainerId) {
                $inventoryItems[] = $this->mapper->inventoryFromInstanceRow($placement, $instance, $template, 'DEFAULT', $ownerCode, 'DEFAULT');
            } elseif ($containerId === $ownerTrashContainerId || ($managementView && $containerId === $systemTrashContainerId)) {
                $trashItems[] = $this->mapper->inventoryFromInstanceRow(
                    $placement,
                    $instance,
                    $template,
                    'TRASH',
                    $ownerCode,
                    'STOS',
                    'ITEM',
                    (int) $instance['id']
                );
            }
        }

        $templateItems = array_map(function (array $template): array {
            return $this->mapper->templateToLegacy($template);
        }, $templatesRaw);
        if (!$managementView) {
            foreach ($templateItems as &$templateItem) {
                unset($templateItem['CONSUMPTION_PROFILE_ID']);
            }
            unset($templateItem);
        }

        $profilesMap = $this->profileService->getProfilesMap($campaignId);
        $pricingEngine = new ShopPricingService();
        foreach ($shops as &$pricedShop) {
            $profile = (array) ($profilesMap[(int) $pricedShop['id']] ?? []);
            foreach (['items', 'shopEntries'] as $collectionKey) {
                $pricedShop[$collectionKey] = array_map(function (array $entry) use (
                    $pricingEngine,
                    $profile,
                    $templateById
                ): array {
                    $templateId = (int) ($entry['INV_ID'] ?? $entry['ID'] ?? 0);
                    $template = $templateById[$templateId] ?? null;
                    if (!$template) return $entry;
                    $pricing = $pricingEngine->calculateForTrade($template, $profile, [
                        'QUANTITY' => $entry['QUANTITY'] ?? 0,
                        'PRICE_OVERRIDE' => $entry['PRICE_OVERRIDE'] ?? null,
                    ], 'buy');
                    $entry['ACTIVE_PRICE'] = (int) $pricing['finalPrice'];
                    $entry['PRICE_BREAKDOWN'] = $pricing['breakdown'];
                    $entry['CURRENCY'] = $pricing['settlementCurrencyCode'];
                    return $entry;
                }, (array) ($pricedShop[$collectionKey] ?? []));
            }
        }
        unset($pricedShop);
        if ($activeShop) {
            foreach ($shops as $pricedShop) {
                if ((int) $pricedShop['id'] === (int) $activeShop['id']) {
                    $activeShop = $pricedShop;
                    break;
                }
            }
        }
        $cachedSuggestions = $activeShop ? $this->suggestionService->getCached($campaignId, (int) $activeShop['id']) : [
            'suggestions' => [],
            'recommendations' => [],
        ];

        $walletBalances = $this->walletService->getBalances($campaignId, $ownerCode);
        $walletBalanceMap = [];
        foreach ($walletBalances as $walletBalance) {
            $walletBalanceMap[(string) $walletBalance['currencyCode']] = (int) $walletBalance['balance'];
        }
        $activeProfile = (array) ($profilesMap[(int) ($activeShop['id'] ?? 0)] ?? []);
        if ($activeProfile) {
            $inventoryItems = array_map(function (array $entry) use (
                $pricingEngine,
                $activeProfile,
                $templateById
            ): array {
                $templateId = (int) ($entry['INV_ID'] ?? 0);
                $template = $templateById[$templateId] ?? null;
                if (!$template) return $entry;
                $pricing = $pricingEngine->calculateForTrade($template, $activeProfile, [
                    'QUANTITY' => 0,
                    'actorCode' => $entry['OWNER'] ?? '',
                ], 'sell');
                $entry['ACTIVE_PRICE'] = (int) $pricing['finalPrice'];
                $entry['PRICE_BREAKDOWN'] = $pricing['breakdown'];
                $entry['CURRENCY'] = $pricing['settlementCurrencyCode'];
                return $entry;
            }, $inventoryItems);
        }
        if ($this->ownerIsInActiveCombat($campaignId, $ownerCode)) {
            foreach ($inventoryItems as &$inventoryItem) {
                if (!empty($inventoryItem['CONSUMPTION'])
                    && empty($inventoryItem['CONSUMPTION']['usableInCombat'])) {
                    $inventoryItem['CONSUMPTION']['disabled'] = true;
                    $inventoryItem['CONSUMPTION']['disabledReason'] = 'Nie można spożyć tego produktu podczas walki.';
                }
            }
            unset($inventoryItem);
        }
        $settlementCurrencyCode = strtolower((string) (
            $activeProfile['pricingConfig']['currencyPolicy']['settlementCurrencyCode']
                ?? $currencyContext['defaultCurrencyCode']
                ?? 'generic'
        ));

        $actors = $access['actors'];
        $allItemInstances = $this->contextBuilder->allItemInstances(
            $visibleInstancePlacements,
            $instanceById,
            $templateById,
            $containerState['containers'],
            $actors,
            $shopsRaw,
            $ownerCode,
            $managementView
        );

        return [
            'shops' => $shops,
            'activeShopId' => $activeShop['id'] ?? null,
            'shopName' => $activeShop['name'] ?? '',
            'shopItems' => $activeShop['items'] ?? [],
            'templateItems' => $templateItems,
            'inventoryItems' => $inventoryItems,
            'trashItems' => $trashItems,
            'shopProfiles' => $managementView ? $profilesMap : [],
            'shopTypes' => $this->catalogService->getShopTypes(),
            'catalogNodes' => $this->catalogService->getCatalogNetwork(),
            'worldProfiles' => $this->catalogService->getWorldProfiles(),
            'itemDictionaries' => $this->catalogService->getItemDictionaries($campaignId),
            'currencyDefinitions' => $currencyContext,
            'mechanics' => $this->mechanicsService->definitionsForSystem($currencyContext['systemCode']),
            'shopSuggestions' => $managementView ? (array) ($cachedSuggestions['suggestions'] ?? []) : [],
            'shopTemplateRecommendations' => $managementView ? (array) ($cachedSuggestions['recommendations'] ?? []) : [],
            'walletBrass' => (int) ($walletBalanceMap[$settlementCurrencyCode] ?? 0),
            'walletCurrencyCode' => $settlementCurrencyCode,
            'primaryCurrencyCode' => $this->walletService->primaryCurrencyCode($campaignId, $ownerCode),
            'walletBalances' => $walletBalances,
            'walletBalanceMap' => $walletBalanceMap,
            // `containers` remains for one compatibility cycle.
            'containers' => $managementView ? $containerMap : [
                'DEFAULT' => null,
                'TRASH' => null,
                'CHARACTER' => $containerMap['CHARACTER'] ?? null,
                'OWNER_TRASH' => $containerMap['OWNER_TRASH'] ?? null,
                'SHOP_BY_ID' => $containerMap['SHOP_BY_ID'] ?? [],
                'ALL' => $visibleContainers,
            ],
            'containerState' => $containerState,
            'context' => [
                'campaignId' => $campaignId,
                'systemCode' => $currencyContext['systemCode'],
                'userId' => $access['userId'],
                'role' => $access['role'] ?: null,
                'ownerCode' => $ownerCode,
                'characterId' => (int) ($authContext['character_id'] ?? 0)
                    ?: $this->contextBuilder->characterIdForOwner($access['visibleClaims'], $ownerCode),
            ],
            'actors' => $actors,
            'permissions' => $access['permissions'],
            'allItemInstances' => $allItemInstances,
        ];
    }

    private function ownerIsInActiveCombat(int $campaignId, string $ownerCode): bool
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('scene_combats') || !$db->tableExists('scene_tokens')) return false;
        $claim = $db->table('shop_owner_claims')->where([
            'campaign_id' => $campaignId, 'owner_code' => strtoupper($ownerCode),
        ])->get()->getRowArray();
        $characterId = (int) ($claim['character_id'] ?? 0);
        if (!$characterId && preg_match('/^CHAR_(\d+)$/', strtoupper($ownerCode), $match)) $characterId = (int) $match[1];
        return $characterId > 0 && $db->table('scene_combats combats')
            ->join('scene_tokens tokens', 'tokens.scene_id = combats.scene_id')
            ->where(['combats.campaign_id' => $campaignId, 'combats.active' => 1, 'tokens.character_id' => $characterId])
            ->countAllResults() > 0;
    }
}
