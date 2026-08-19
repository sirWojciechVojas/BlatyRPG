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

trait ShopModuleControllerPart4
{
    public function updateItemInstance($campaignId, $instanceId)
    {
        $auth = $this->resolveAuth();
        $input = $this->request->getJSON(true) ?: [];
        $instance = $this->itemInstanceModel
            ->where('campaign_id', (int) $campaignId)
            ->where('id', (int) $instanceId)
            ->first();
        if (!$instance) {
            return $this->failNotFound('Item instance not found.');
        }

        $placement = $this->containerInstanceItemModel
            ->where('campaign_id', (int) $campaignId)
            ->where('instance_id', (int) $instanceId)
            ->first();
        if (!$placement) {
            return $this->failNotFound('Item placement not found.');
        }

        if (!$this->authContextService->isGmOrAdmin($auth)) {
            $ownerCode = strtoupper((string) (($input['ownerCode'] ?? '') ?: $this->request->getGet('ownerCode')));
            $ownerCheck = $this->authorizationService->assertOwnerAccess($auth, (int) $campaignId, $ownerCode);
            if (!$ownerCheck['ok']) {
                return $this->respondError($ownerCheck);
            }
            $container = $this->containerModel
                ->where('campaign_id', (int) $campaignId)
                ->where('id', (int) $placement['container_id'])
                ->where('owner_code', $ownerCode)
                ->first();
            if (!$container) {
                return $this->respondError(['code' => 'forbidden_owner', 'status' => 403]);
            }
        }

        $meta = (array) ($instance['data_override_json'] ?? []);
        $mapping = [
            'description' => 'DESCRIPTION',
            'details' => 'DETAILS',
            'icon' => 'IMG_CLASS',
            'imgClass' => 'IMG_CLASS',
            'price' => 'PRIZE',
            'charge' => 'CHARGE',
            'itemClass' => 'ITEM_CLASS',
            'itemGenre' => 'ITEM_GENRE',
            'attributes' => 'ATTRIBUTES',
            'currencyCode' => 'CURRENCY',
            'weapon' => 'WEAPON',
        ];
        foreach ($mapping as $source => $target) {
            if (array_key_exists($source, $input)) {
                $meta[$target] = $input[$source];
            }
        }
        $name = array_key_exists('name', $input)
            ? trim((string) $input['name'])
            : (string) ($instance['name_override'] ?? '');
        $description = array_key_exists('description', $input)
            ? (string) $input['description']
            : (string) ($instance['note'] ?? '');
        $meta['IMG_CLASS'] = $this->itemIconResolver->resolve([
            'NAME' => $name,
            'DESCRIPTION' => $description,
            'ITEM_CLASS' => $meta['ITEM_CLASS'] ?? '',
            'ITEM_GENRE' => $meta['ITEM_GENRE'] ?? '',
        ], (string) ($meta['IMG_CLASS'] ?? 'v0001'), true);
        $this->itemInstanceModel->update((int) $instanceId, [
            'name_override' => $name,
            'note' => $description,
            'data_override_json' => $meta,
        ]);
        if (array_key_exists('price', $input)) {
            $this->containerInstanceItemModel->update((int) $placement['id'], [
                'price_override' => max(0, (int) $input['price']),
            ]);
        }

        return $this->response->setJSON([
            'ok' => true,
            'itemInstance' => $this->itemInstanceModel->find((int) $instanceId),
            'containerState' => $this->containerService->getContainers(
                (int) $campaignId,
                strtoupper((string) ($input['ownerCode'] ?? 'BG1'))
            ),
        ]);
    }

    public function getContainers($campaignId)
    {
        $ownerCode = strtoupper((string) ($this->request->getGet('ownerCode') ?? 'BG1'));
        $auth = $this->resolveAuth();
        $ownerCheck = $this->authorizationService->assertOwnerAccess((array) $auth, (int) $campaignId, $ownerCode);
        if (!$ownerCheck['ok']) {
            return $this->respondError($ownerCheck);
        }

        $data = $this->authContextService->isGmOrAdmin((array) $auth)
            ? $this->containerService->getContainers((int) $campaignId, $ownerCode)
            : $this->containerService->getContainersForOwner((int) $campaignId, $ownerCode);
        return $this->response->setJSON($data);
    }

    public function moveContainer($campaignId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        if (isset($payload['moves']) && is_array($payload['moves'])) {
            $ownerCode = strtoupper((string) ($payload['ownerCode'] ?? 'BG1'));
            $payload['moves'] = array_map(static function (array $move) use ($ownerCode): array {
                $move['ownerCode'] = strtoupper((string) ($move['ownerCode'] ?? $ownerCode));
                return $move;
            }, $payload['moves']);
        }
        $result = isset($payload['moves']) && is_array($payload['moves'])
            ? $this->containerService->moveMany((int) $campaignId, $payload['moves'])
            : $this->containerService->move((int) $campaignId, $payload);

        if (!$result['ok']) {
            return $this->respondDomainError($result);
        }

        return $this->response->setJSON($result);
    }

    public function buyFromContainer($campaignId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        $result = $this->containerService->buyFromShop((int) $campaignId, $payload);

        if (!$result['ok']) {
            return $this->respondDomainError($result);
        }

        return $this->response->setJSON($result);
    }

    public function setContainerQuantities($campaignId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        $result = $this->containerService->setTemplateQuantities(
            (int) $campaignId,
            array_values((array) ($payload['changes'] ?? [])),
            strtoupper((string) ($payload['ownerCode'] ?? 'BG1'))
        );
        if (!$result['ok']) {
            return $this->respondDomainError($result);
        }

        return $this->response->setJSON($result);
    }

    public function trashContainerItem($campaignId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        $result = $this->containerService->trash((int) $campaignId, $payload);

        if (!$result['ok']) {
            return $this->respondDomainError($result);
        }

        return $this->response->setJSON($result);
    }

    public function restoreContainerItem($campaignId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        $result = $this->containerService->restore((int) $campaignId, $payload);

        if (!$result['ok']) {
            return $this->respondDomainError($result);
        }

        return $this->response->setJSON($result);
    }

    public function mergeContainerItems($campaignId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        $result = $this->containerService->merge((int) $campaignId, $payload);

        if (!$result['ok']) {
            return $this->respondDomainError($result);
        }

        return $this->response->setJSON($result);
    }

    public function tradeBuy($campaignId)
    {
        $auth = $this->resolveAuth();
        $payload = $this->request->getJSON(true) ?: [];
        $ownerCode = strtoupper((string) ($payload['ownerCode'] ?? ''));

        $ownerCheck = $this->authorizationService->assertOwnerAccess((array) $auth, (int) $campaignId, $ownerCode);
        if (!$ownerCheck['ok']) {
            return $this->respondError($ownerCheck);
        }

        $result = $this->tradeService->buy(
            (int) $campaignId,
            $payload,
            (array) $auth,
            $this->request->getHeaderLine('Idempotency-Key') ?: null
        );

        if (!($result['ok'] ?? false)) {
            return $this->respondDomainError($result);
        }

        return $this->response->setJSON($result);
    }

    public function quoteTradeBuyPayment($campaignId)
    {
        $auth = $this->resolveAuth();
        $payload = $this->request->getJSON(true) ?: [];
        $ownerCode = strtoupper((string) ($payload['ownerCode'] ?? ''));
        $ownerCheck = $this->authorizationService->assertOwnerAccess((array) $auth, (int) $campaignId, $ownerCode);
        if (!$ownerCheck['ok']) {
            return $this->respondError($ownerCheck);
        }
        $result = $this->tradeService->quoteBuyPayment((int) $campaignId, $payload, (array) $auth);
        return !($result['ok'] ?? false)
            ? $this->respondDomainError($result)
            : $this->response->setJSON($result);
    }
}
