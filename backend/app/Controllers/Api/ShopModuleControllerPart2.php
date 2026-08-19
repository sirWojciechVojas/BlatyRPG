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

trait ShopModuleControllerPart2
{
    public function deleteShop($campaignId, $shopId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $shopsCount = $this->shopModel
            ->where('campaign_id', (int) $campaignId)
            ->where('deleted_at', null)
            ->countAllResults();

        if ($shopsCount <= 1) {
            return $this->fail(['code' => 'cannot_delete_last_shop', 'message' => 'At least one shop must remain.'], 409);
        }

        $shop = $this->shopModel
            ->where('campaign_id', (int) $campaignId)
            ->where('id', (int) $shopId)
            ->where('deleted_at', null)
            ->first();

        if (!$shop) {
            return $this->failNotFound('Shop not found.');
        }

        $this->shopModel->delete((int) $shopId);
        $this->containerModel
            ->where('campaign_id', (int) $campaignId)
            ->where('shop_id', (int) $shopId)
            ->set(['is_active' => 0])
            ->update();

        return $this->response->setJSON(['message' => 'Shop deleted.']);
    }

    public function duplicateShop($campaignId, $shopId)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }
        $source = $this->shopModel
            ->where('campaign_id', (int) $campaignId)
            ->where('id', (int) $shopId)
            ->where('deleted_at', null)
            ->first();
        if (!$source) {
            return $this->failNotFound('Shop not found.');
        }

        $input = $this->request->getJSON(true) ?: [];
        $copyOffer = ($input['copyMode'] ?? 'profile') === 'profile_with_offer';
        $name = trim((string) ($input['name'] ?? ('Kopia — '.$source['name'])));
        $db = \Config\Database::connect();
        $db->transBegin();
        $this->shopModel->insert([
            'campaign_id' => (int) $campaignId,
            'name' => $name,
            'owner_code' => (string) ($source['owner_code'] ?? 'BG1'),
            'owner_name' => (string) ($source['owner_name'] ?? ''),
            'is_active' => 0,
        ]);
        $newShopId = (int) $this->shopModel->getInsertID();
        $profile = $this->profileService->getProfile((int) $campaignId, (int) $shopId) ?: [];
        $profile['shopId'] = $newShopId;
        $profile['signboardName'] = $name;
        $this->profileService->upsertProfile((int) $campaignId, $newShopId, $profile);

        $ownerCode = strtoupper((string) ($source['owner_code'] ?? 'BG1'));
        $containerMap = $this->containerService->ensureBaseContainers((int) $campaignId, $ownerCode);
        $sourceContainerId = (int) ($containerMap['SHOP_BY_ID'][(int) $shopId] ?? 0);
        $targetContainerId = (int) ($containerMap['SHOP_BY_ID'][$newShopId] ?? 0);
        if ($copyOffer && $sourceContainerId && $targetContainerId) {
            $templateRows = $this->containerTemplateItemModel
                ->where('campaign_id', (int) $campaignId)
                ->where('container_id', $sourceContainerId)
                ->findAll();
            foreach ($templateRows as $row) {
                $this->containerTemplateItemModel->insert([
                    'campaign_id' => (int) $campaignId,
                    'container_id' => $targetContainerId,
                    'template_id' => (int) $row['template_id'],
                    'quantity' => $row['quantity'],
                    'price_override' => $row['price_override'],
                ]);
            }
            $instanceRows = $this->containerInstanceItemModel
                ->where('campaign_id', (int) $campaignId)
                ->where('container_id', $sourceContainerId)
                ->findAll();
            foreach ($instanceRows as $placement) {
                $instance = $this->itemInstanceModel->find((int) $placement['instance_id']);
                if (!$instance) {
                    continue;
                }
                $this->itemInstanceModel->insert([
                    'campaign_id' => (int) $campaignId,
                    'template_id' => (int) $instance['template_id'],
                    'name_override' => $instance['name_override'],
                    'data_override_json' => $instance['data_override_json'],
                    'note' => $instance['note'],
                ]);
                $this->containerInstanceItemModel->insert([
                    'campaign_id' => (int) $campaignId,
                    'container_id' => $targetContainerId,
                    'instance_id' => (int) $this->itemInstanceModel->getInsertID(),
                    'price_override' => $placement['price_override'],
                ]);
            }
        }

        if (!$db->transStatus()) {
            $db->transRollback();
            return $this->respondDomainError(['code' => 'transaction_failed', 'status' => 500]);
        }
        $db->transCommit();
        $created = $this->shopModel->find($newShopId);

        return $this->response->setStatusCode(201)->setJSON([
            'ok' => true,
            'shop' => $this->mapper->shopToLegacy($created),
            'profile' => $this->profileService->getProfile((int) $campaignId, $newShopId),
            'containerState' => $this->containerService->getContainers((int) $campaignId, $ownerCode),
        ]);
    }

    public function updateShopActivation($campaignId, $shopId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $shop = $this->shopModel
            ->where('campaign_id', (int) $campaignId)
            ->where('id', (int) $shopId)
            ->where('deleted_at', null)
            ->first();

        if (!$shop) {
            return $this->failNotFound('Shop not found.');
        }

        $input = $this->request->getJSON(true) ?: [];
        $isActive = !array_key_exists('isActive', $input) || (bool) $input['isActive'];

        $this->shopModel->update((int) $shopId, ['is_active' => $isActive ? 1 : 0]);

        return $this->response->setJSON([
            'ok' => true,
            'shopId' => (int) $shopId,
            'isActive' => $isActive,
        ]);
    }

    public function getShopProfile($campaignId, $shopId)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }
        $profile = $this->profileService->getProfile((int) $campaignId, (int) $shopId);
        if (!$profile) {
            return $this->failNotFound('Profile not found.');
        }

        return $this->response->setJSON($profile);
    }

    public function putShopProfile($campaignId, $shopId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        $errors = $this->profileService->validate($payload);
        if ($errors) {
            return $this->fail([
                'code' => 'invalid_shop_profile',
                'message' => 'Shop profile validation failed.',
                'errors' => $errors,
            ], 422);
        }
        $profile = $this->profileService->upsertProfile(
            (int) $campaignId,
            (int) $shopId,
            $payload,
            isset($auth['user_id']) ? (int) $auth['user_id'] : null
        );

        if (!$profile) {
            return $this->failNotFound('Shop not found.');
        }

        return $this->response->setJSON([
            'message' => 'Profile saved.',
            'profile' => $profile,
        ]);
    }

    public function listTemplates($campaignId)
    {
        $search = trim((string) ($this->request->getGet('search') ?? ''));
        $itemClass = strtoupper(trim((string) ($this->request->getGet('itemClass') ?? '')));
        $status = strtolower(trim((string) ($this->request->getGet('status') ?? 'active')));
        if (!in_array($status, ['active', 'archived', 'all'], true)) {
            return $this->fail(['code' => 'invalid_status', 'message' => 'Unsupported template status.'], 400);
        }

        $query = $this->templateModel;
        if ($status === 'archived') {
            $query = $query->onlyDeleted();
        } elseif ($status === 'all') {
            $query = $query->withDeleted();
        }
        $query->where('campaign_id', (int) $campaignId);

        if ($search !== '') {
            $query->groupStart()
                ->like('name', $search)
                ->orLike('description', $search)
                ->groupEnd();
        }

        if ($itemClass !== '') {
            $query->where('item_class', $itemClass);
        }

        $rows = $query->orderBy('id', 'ASC')->findAll();
        $items = array_map(function (array $row): array {
            return array_merge($this->mapper->templateToLegacy($row), [
                'status' => empty($row['deleted_at']) ? 'active' : 'archived',
                'archivedAt' => $row['deleted_at'] ?? null,
            ]);
        }, $rows);

        return $this->response->setJSON([
            'count' => count($items),
            'items' => $items,
        ]);
    }

    public function createTemplate($campaignId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $input = $this->request->getJSON(true) ?: [];
        if (trim((string) ($input['NAME'] ?? '')) === '') {
            return $this->fail(['code' => 'invalid_payload', 'message' => 'NAME is required.'], 400);
        }

        $record = $this->legacyTemplateInputToRecord((int) $campaignId, $input);
        $this->templateModel->insert($record);
        $saved = $this->templateModel->find((int) $this->templateModel->getInsertID());

        return $this->response->setStatusCode(201)->setJSON([
            'message' => 'Template created.',
            'template' => $this->mapper->templateToLegacy($saved),
        ]);
    }
}
