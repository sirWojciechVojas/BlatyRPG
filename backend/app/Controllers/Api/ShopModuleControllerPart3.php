<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ShopModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopContainerModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopItemInstanceModel;
use App\Services\Shop\AuthContextService;
use App\Services\Shop\ShopAuthorizationService;
use App\Services\Shop\ShopBootstrapService;
use App\Services\Shop\ShopCatalogService;
use App\Services\Shop\ShopContainerService;
use App\Services\Shop\ShopCurrencyService;
use App\Services\Shop\ShopLegacyMapper;
use App\Services\Shop\ShopPricingService;
use App\Services\Shop\ShopProfileService;
use App\Services\Shop\ShopSuggestionService;
use App\Services\Shop\ShopTradeLedgerService;
use App\Services\Shop\ShopTradeService;

trait ShopModuleControllerPart3
{
    public function updateTemplate($campaignId, $templateId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $existing = $this->templateModel
            ->where('campaign_id', (int) $campaignId)
            ->where('id', (int) $templateId)
            ->where('deleted_at', null)
            ->first();

        if (!$existing) {
            return $this->failNotFound('Template not found.');
        }

        $input = $this->request->getJSON(true) ?: [];
        $record = $this->legacyTemplateInputToRecord((int) $campaignId, $input, $existing);

        $this->templateModel->update((int) $templateId, $record);
        $saved = $this->templateModel->find((int) $templateId);

        return $this->response->setJSON([
            'message' => 'Template updated.',
            'template' => $this->mapper->templateToLegacy($saved),
        ]);
    }

    public function deleteTemplate($campaignId, $templateId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $existing = $this->templateModel
            ->where('campaign_id', (int) $campaignId)
            ->where('id', (int) $templateId)
            ->where('deleted_at', null)
            ->first();

        if (!$existing) {
            return $this->failNotFound('Template not found.');
        }

        $this->templateModel->delete((int) $templateId);

        return $this->response->setJSON(['message' => 'Template deleted.']);
    }

    public function restoreTemplate($campaignId, $templateId)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $existing = $this->templateModel
            ->withDeleted()
            ->where('campaign_id', (int) $campaignId)
            ->where('id', (int) $templateId)
            ->first();
        if (!$existing) {
            return $this->failNotFound('Template not found.');
        }

        \Config\Database::connect()->table('shop_templates')
            ->where('campaign_id', (int) $campaignId)
            ->where('id', (int) $templateId)
            ->update(['deleted_at' => null, 'updated_at' => date('Y-m-d H:i:s')]);
        $restored = $this->templateModel->find((int) $templateId);

        return $this->response->setJSON([
            'ok' => true,
            'template' => $this->mapper->templateToLegacy($restored),
        ]);
    }

    public function duplicateTemplate($campaignId, $templateId)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $source = $this->templateModel
            ->where('campaign_id', (int) $campaignId)
            ->where('id', (int) $templateId)
            ->first();
        if (!$source) {
            return $this->failNotFound('Template not found.');
        }
        $input = $this->request->getJSON(true) ?: [];
        $copy = array_intersect_key($source, array_flip([
            'campaign_id', 'name', 'description', 'details', 'item_class',
            'item_id', 'item_genre', 'img_class', 'prize', 'charge', 'draft',
            'currency_code', 'weapon_json', 'attributes_json',
            'mechanics_json', 'mechanics_mode',
        ]));
        $copy['name'] = trim((string) ($input['name'] ?? ('Kopia — '.$source['name'])));
        $copy['campaign_id'] = (int) $campaignId;
        $this->templateModel->insert($copy);
        $created = $this->templateModel->find((int) $this->templateModel->getInsertID());

        return $this->response->setStatusCode(201)->setJSON([
            'ok' => true,
            'template' => $this->mapper->templateToLegacy($created),
        ]);
    }

    public function createItemInstance($campaignId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $input = $this->request->getJSON(true) ?: [];
        $templateId = (int) ($input['templateId'] ?? 0);
        $containerId = (int) ($input['containerId'] ?? 0);
        $ownerCode = strtoupper((string) ($input['ownerCode'] ?? 'BG1'));
        if (!$containerId) {
            $containerMap = $this->containerService->ensureBaseContainers((int) $campaignId, $ownerCode);
            $containerId = (int) ($containerMap['DEFAULT'] ?? 0);
        }
        $template = $this->templateModel
            ->where('campaign_id', (int) $campaignId)
            ->where('id', $templateId)
            ->where('deleted_at', null)
            ->first();
        $container = $this->containerModel
            ->where('campaign_id', (int) $campaignId)
            ->where('id', $containerId)
            ->where('is_active', 1)
            ->first();

        if (!$template || !$container) {
            return $this->fail([
                'code' => 'invalid_instance_target',
                'message' => 'Template or target container not found.',
            ], 400);
        }

        $attributes = $this->normalizeAttributeCodes(
            $input['attributes'] ?? $template['attributes_json'] ?? []
        );
        $name = trim((string) ($input['name'] ?? $template['name'] ?? ''));
        $description = (string) ($input['description'] ?? $template['description'] ?? '');
        $meta = [
            'DESCRIPTION' => $description,
            'DETAILS' => (string) ($input['details'] ?? $template['details'] ?? ''),
            'ITEM_CLASS' => strtoupper((string) ($input['itemClass'] ?? $template['item_class'] ?? 'TOOL')),
            'ITEM_GENRE' => strtoupper((string) ($input['itemGenre'] ?? $template['item_genre'] ?? 'UTILITY')),
            'IMG_CLASS' => (string) ($input['imgClass'] ?? $input['icon'] ?? $template['img_class'] ?? 'v0001'),
            'PRIZE' => max(0, (int) ($input['price'] ?? $template['prize'] ?? 0)),
            'CURRENCY' => (string) ($input['currencyCode'] ?? $template['currency_code'] ?? 'generic'),
            'CHARGE' => max(0, (int) ($input['charge'] ?? $template['charge'] ?? 0)),
            'ATTRIBUTES' => $attributes,
            'WEAPON' => (array) ($input['weapon'] ?? $template['weapon_json'] ?? []),
        ];
        $meta['IMG_CLASS'] = $this->itemIconResolver->resolve([
            'NAME' => $name,
            'DESCRIPTION' => $description,
            'ITEM_CLASS' => $meta['ITEM_CLASS'],
            'ITEM_GENRE' => $meta['ITEM_GENRE'],
        ], (string) $meta['IMG_CLASS'], true);
        $ownerCode = strtoupper((string) ($container['owner_code'] ?? $ownerCode));

        $db = \Config\Database::connect();
        $db->transBegin();
        $this->itemInstanceModel->insert([
            'campaign_id' => (int) $campaignId,
            'template_id' => $templateId,
            'name_override' => $name,
            'note' => $description,
            'data_override_json' => $meta,
        ]);
        $instanceId = (int) $this->itemInstanceModel->getInsertID();
        if (!$instanceId) {
            $db->transRollback();
            return $this->fail(['code' => 'instance_create_failed'], 500);
        }

        $this->containerInstanceItemModel->insert([
            'campaign_id' => (int) $campaignId,
            'container_id' => $containerId,
            'instance_id' => $instanceId,
            'price_override' => $meta['PRIZE'],
        ]);
        $placementId = (int) $this->containerInstanceItemModel->getInsertID();
        if (!$placementId || !$db->transStatus()) {
            $db->transRollback();
            return $this->fail(['code' => 'instance_placement_failed'], 500);
        }
        $db->transCommit();

        $instance = $this->itemInstanceModel->find($instanceId);
        $placement = $this->containerInstanceItemModel->find($placementId);
        $containerType = strtoupper((string) ($container['container_type'] ?? 'SYSTEM'));
        $ownerOpt = $containerType === 'CHARACTER'
            ? strtoupper((string) ($container['owner_code'] ?? $ownerCode))
            : ($containerType === 'TRASH' ? 'TRASH' : 'DEFAULT');
        $itemPlace = $containerType === 'SHOP'
            ? 'STOISKO'
            : ($containerType === 'CHARACTER' ? 'PLECY' : ($containerType === 'TRASH' ? 'STOS' : 'DEFAULT'));

        return $this->response->setStatusCode(201)->setJSON([
            'ok' => true,
            'itemInstance' => $instance,
            'placement' => $placement,
            'item' => $this->mapper->inventoryFromInstanceRow(
                $placement,
                $instance,
                $template,
                $ownerOpt,
                $ownerCode,
                $itemPlace
            ),
            'containerState' => $this->containerService->getContainers((int) $campaignId, $ownerCode),
        ]);
    }
}
