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

trait ShopSuggestionServicePart2
{
    public function apply(int $campaignId, int $shopId, array $payload): array
    {
        $cached = $this->getCached($campaignId, $shopId);
        $suggestions = (array) $cached['suggestions'];

        $requestedIds = array_values(array_filter(array_map(function ($id): string {
            return (string) $id;
        }, (array) ($payload['suggestionIds'] ?? []))));

        if (!$requestedIds) {
            foreach ((array) ($payload['suggestions'] ?? []) as $entry) {
                $id = (string) ($entry['suggestionId'] ?? '');
                if ($id) {
                    $requestedIds[] = $id;
                }
            }
        }

        if (!$requestedIds) {
            $requestedIds = array_map(function ($entry): string {
                return (string) ($entry['suggestionId'] ?? '');
            }, $suggestions);
        }

        $map = [];
        foreach ($suggestions as $entry) {
            $id = (string) ($entry['suggestionId'] ?? '');
            if ($id) {
                $map[$id] = $entry;
            }
        }
        foreach ((array) ($payload['suggestions'] ?? []) as $entry) {
            $id = (string) ($entry['suggestionId'] ?? '');
            if ($id) {
                $map[$id] = array_merge((array) ($map[$id] ?? []), (array) $entry);
            }
        }

        $ownerCode = strtoupper((string) ($payload['ownerCode'] ?? 'BG1'));
        $containers = $this->containerService->ensureBaseContainers($campaignId, $ownerCode);
        $shopContainerId = (int) ($containers['SHOP_BY_ID'][$shopId] ?? 0);
        if (!$shopContainerId) {
            return ['ok' => false, 'code' => 'not_found', 'status' => 404];
        }

        $applied = 0;
        $instances = 0;
        $appliedSuggestions = [];
        $suggestionTemplateMap = [];
        $this->db->transBegin();

        if (!empty($payload['replaceExisting'])) {
            $placements = $this->containerInstanceItemModel
                ->where('campaign_id', $campaignId)
                ->where('container_id', $shopContainerId)
                ->findAll();
            $instanceIds = array_values(array_filter(array_map(static function (array $placement): int {
                return (int) ($placement['instance_id'] ?? 0);
            }, $placements)));
            if ($instanceIds) {
                $this->containerInstanceItemModel
                    ->where('campaign_id', $campaignId)
                    ->where('container_id', $shopContainerId)
                    ->delete();
                $this->itemInstanceModel
                    ->where('campaign_id', $campaignId)
                    ->whereIn('id', $instanceIds)
                    ->delete();
            }
            $this->containerTemplateItemModel
                ->where('campaign_id', $campaignId)
                ->where('container_id', $shopContainerId)
                ->delete();
        }

        foreach ($requestedIds as $id) {
            $suggestion = $map[$id] ?? null;
            if (!$suggestion) {
                $this->db->transRollback();
                return ['ok' => false, 'code' => 'suggestion_not_found', 'status' => 404];
            }

            $templateId = (int) ($suggestion['templateId'] ?? 0);
            if (!$templateId && (string) ($suggestion['action'] ?? '') === 'create_draft') {
                $draft = (array) ($suggestion['draftTemplate'] ?? []);
                $created = $this->createTemplateFromDraft($campaignId, $draft);
                $templateId = (int) ($created['id'] ?? 0);
                if ($templateId) {
                    $suggestion['templateId'] = $templateId;
                    $suggestion['action'] = 'use_existing';
                    $suggestionTemplateMap[(string) $id] = $templateId;
                }
            }

            if (!$templateId) {
                $this->db->transRollback();
                return ['ok' => false, 'code' => 'invalid_template', 'status' => 400];
            }
            $templateExists = $this->templateModel
                ->where('campaign_id', $campaignId)
                ->where('deleted_at', null)
                ->find($templateId);
            if (!$templateExists) {
                $this->db->transRollback();
                return ['ok' => false, 'code' => 'template_campaign_mismatch', 'status' => 403];
            }
            $suggestedIcon = (string) ($suggestion['imgClass'] ?? $suggestion['draftTemplate']['IMG_CLASS'] ?? '');
            $resolvedIcon = $this->itemIconResolver->resolve($templateExists, $suggestedIcon ?: null);
            if ($resolvedIcon !== (string) ($templateExists['img_class'] ?? '')) {
                $this->templateModel->update($templateId, ['img_class' => $resolvedIcon]);
                $templateExists['img_class'] = $resolvedIcon;
            }

            $quantity = max(1, (int) ($suggestion['quantity'] ?? 1));
            $instances += $quantity;

            for ($unit = 0; $unit < $quantity; $unit++) {
                $instanceId = $this->itemInstanceModel->insert([
                    'campaign_id' => $campaignId,
                    'template_id' => $templateId,
                    'name_override' => (string) ($templateExists['name'] ?? ''),
                    'note' => (string) ($templateExists['description'] ?? ''),
                    'data_override_json' => [
                        'DETAILS' => (string) ($templateExists['details'] ?? ''),
                        'ITEM_CLASS' => strtoupper((string) ($templateExists['item_class'] ?? '')),
                        'ITEM_GENRE' => strtoupper((string) ($templateExists['item_genre'] ?? '')),
                        'IMG_CLASS' => $resolvedIcon,
                        'PRIZE' => (int) ($templateExists['prize'] ?? 0),
                        'CURRENCY' => (string) ($templateExists['currency_code'] ?? 'generic'),
                        'CHARGE' => (int) ($templateExists['charge'] ?? 0),
                        'ATTRIBUTES' => array_values((array) ($templateExists['attributes_json'] ?? [])),
                        'WEAPON' => (array) ($templateExists['weapon_json'] ?? []),
                        '_suggestionId' => (string) ($suggestion['suggestionId'] ?? ''),
                    ],
                ], true);
                if (!$instanceId) {
                    $this->db->transRollback();
                    return ['ok' => false, 'code' => 'transaction_failed', 'status' => 500];
                }
                $placementId = $this->containerInstanceItemModel->insert([
                    'campaign_id' => $campaignId,
                    'container_id' => $shopContainerId,
                    'instance_id' => (int) $instanceId,
                    'price_override' => null,
                ], true);
                if (!$placementId) {
                    $this->db->transRollback();
                    return ['ok' => false, 'code' => 'transaction_failed', 'status' => 500];
                }
            }

            $applied++;
            $appliedSuggestions[] = $suggestion;
        }

        if (!$this->db->transStatus()) {
            $this->db->transRollback();
            return ['ok' => false, 'code' => 'transaction_failed', 'status' => 500];
        }
        $this->db->transCommit();

        $updatedCache = $suggestionTemplateMap
            ? $this->persistSuggestionTemplateLinks($campaignId, $shopId, $suggestionTemplateMap)
            : $cached;

        return [
            'ok' => true,
            'applied' => $applied,
            'appliedInstances' => $instances,
            'suggestions' => $appliedSuggestions,
            'cachedSuggestions' => (array) ($updatedCache['suggestions'] ?? []),
            'recommendations' => (array) ($updatedCache['recommendations'] ?? []),
            'suggestionTemplateMap' => $suggestionTemplateMap,
            'containerState' => $this->containerService->getContainers($campaignId, $ownerCode),
        ];
    }

    public function materialize(int $campaignId, int $shopId, array $payload): array
    {
        $mode = (string) ($payload['mode'] ?? 'template_plus_item');
        $suggestionId = (string) ($payload['suggestionId'] ?? '');

        if (!$suggestionId) {
            return ['created' => 0, 'applied' => 0, 'suggestionTemplateMap' => []];
        }

        $cached = $this->getCached($campaignId, $shopId);
        $all = array_merge((array) $cached['suggestions'], (array) $cached['recommendations']);

        $selected = null;
        foreach ($all as $entry) {
            if ((string) ($entry['suggestionId'] ?? '') === $suggestionId) {
                $selected = $entry;
                break;
            }
        }

        if (!$selected) {
            return ['created' => 0, 'applied' => 0, 'suggestionTemplateMap' => []];
        }

        if (!in_array($mode, ['template_only', 'template_plus_item', 'item_only'], true)) {
            return ['created' => 0, 'applied' => 0, 'suggestionTemplateMap' => []];
        }

        $created = 0;
        $templateId = (int) ($selected['templateId'] ?? 0);

        if (
            !$templateId
            && $mode !== 'item_only'
            && (string) ($selected['action'] ?? '') === 'create_draft'
        ) {
            $draft = (array) ($selected['draftTemplate'] ?? []);
            $record = $this->createTemplateFromDraft($campaignId, $draft);
            $templateId = (int) ($record['id'] ?? 0);
            if ($templateId) {
                $created = !empty($record['_wasCreated']) ? 1 : 0;
                $selected['templateId'] = $templateId;
                $selected['action'] = 'use_existing';
                $cached = $this->persistSuggestionTemplateLinks(
                    $campaignId,
                    $shopId,
                    [$suggestionId => $templateId]
                );
            }
        }

        if ($mode === 'template_only') {
            return [
                'created' => $created,
                'applied' => 0,
                'suggestionTemplateMap' => [$suggestionId => $templateId],
                'cachedSuggestions' => (array) ($cached['suggestions'] ?? []),
                'recommendations' => (array) ($cached['recommendations'] ?? []),
            ];
        }

        if (!$templateId) {
            return [
                'created' => $created,
                'applied' => 0,
                'suggestionTemplateMap' => [$suggestionId => null],
                'cachedSuggestions' => (array) ($cached['suggestions'] ?? []),
                'recommendations' => (array) ($cached['recommendations'] ?? []),
            ];
        }

        $applyResult = $this->apply($campaignId, $shopId, [
            'suggestionIds' => [$suggestionId],
            'suggestions' => [array_merge($selected, [
                'templateId' => $templateId,
                'action' => 'use_existing',
                'quantity' => 1,
                'variantId' => (string) ($payload['variantId'] ?? ''),
            ])],
            'ownerCode' => (string) ($payload['ownerCode'] ?? 'BG1'),
        ]);

        return [
            'created' => $created,
            'applied' => (int) ($applyResult['applied'] ?? 0),
            'suggestionTemplateMap' => [$suggestionId => $templateId],
            'cachedSuggestions' => (array) ($applyResult['cachedSuggestions'] ?? $cached['suggestions'] ?? []),
            'recommendations' => (array) ($applyResult['recommendations'] ?? $cached['recommendations'] ?? []),
            'containerState' => $applyResult['containerState'] ?? null,
        ];
    }

    private function persistSuggestionTemplateLinks(
        int $campaignId,
        int $shopId,
        array $suggestionTemplateMap
    ): array {
        $cached = $this->getCached($campaignId, $shopId);
        $patchCollection = static function (array $entries) use ($suggestionTemplateMap): array {
            return array_map(static function ($entry) use ($suggestionTemplateMap) {
                if (!is_array($entry)) {
                    return $entry;
                }
                $suggestionId = (string) ($entry['suggestionId'] ?? '');
                $templateId = (int) ($suggestionTemplateMap[$suggestionId] ?? 0);
                if (!$templateId) {
                    return $entry;
                }
                $entry['templateId'] = $templateId;
                $entry['action'] = 'use_existing';
                return $entry;
            }, $entries);
        };

        $suggestions = $patchCollection((array) ($cached['suggestions'] ?? []));
        $recommendations = $patchCollection((array) ($cached['recommendations'] ?? []));
        $this->saveCache(
            $campaignId,
            $shopId,
            $suggestions,
            $recommendations,
            (string) ($cached['profileHash'] ?? '')
        );

        return array_merge($cached, [
            'suggestions' => $suggestions,
            'recommendations' => $recommendations,
        ]);
    }
}
