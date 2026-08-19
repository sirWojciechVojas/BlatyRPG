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

trait ShopModuleControllerPart5
{
    public function tradeSell($campaignId)
    {
        $auth = $this->resolveAuth();
        $payload = $this->request->getJSON(true) ?: [];
        $ownerCode = strtoupper((string) ($payload['ownerCode'] ?? ''));

        $ownerCheck = $this->authorizationService->assertOwnerAccess((array) $auth, (int) $campaignId, $ownerCode);
        if (!$ownerCheck['ok']) {
            return $this->respondError($ownerCheck);
        }

        $result = $this->tradeService->sell(
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

    public function listTradeLedger($campaignId)
    {
        $auth = $this->resolveAuth();
        $isGm = $this->authContextService->isGmOrAdmin((array) $auth);
        $requestedOwnerCode = strtoupper(trim((string) ($this->request->getGet('ownerCode') ?? '')));
        if (!$isGm) {
            if ($requestedOwnerCode === '') {
                return $this->respondError(['code' => 'owner_required', 'status' => 403]);
            }
            $ownerCheck = $this->authorizationService->assertOwnerAccess(
                (array) $auth,
                (int) $campaignId,
                $requestedOwnerCode
            );
            if (!$ownerCheck['ok']) {
                return $this->respondError($ownerCheck);
            }
        }

        $filters = [
            'shopId' => $this->request->getGet('shopId'),
            'ownerCode' => $this->request->getGet('ownerCode'),
            'transactionType' => $this->request->getGet('transactionType'),
            'status' => $this->request->getGet('status'),
            'dateFrom' => $this->request->getGet('dateFrom'),
            'dateTo' => $this->request->getGet('dateTo'),
            'item' => $this->request->getGet('item'),
            'onlyReversed' => filter_var($this->request->getGet('onlyReversed'), FILTER_VALIDATE_BOOLEAN),
            'onlyRedone' => filter_var($this->request->getGet('onlyRedone'), FILTER_VALIDATE_BOOLEAN),
            'onlyCorrected' => filter_var($this->request->getGet('onlyCorrected'), FILTER_VALIDATE_BOOLEAN),
            'page' => $this->request->getGet('page'),
            'pageSize' => $this->request->getGet('pageSize'),
        ];
        if (!$isGm) {
            $filters['ownerCode'] = $requestedOwnerCode;
        }

        return $this->response->setJSON($this->tradeLedgerService->list((int) $campaignId, $filters));
    }

    public function reverseTradeLedger($campaignId, $transactionId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        $result = $this->tradeLedgerService->reverse((int) $campaignId, (int) $transactionId, (array) $auth, $payload);
        if (!($result['ok'] ?? false)) {
            return $this->respondDomainError($result);
        }

        return $this->response->setJSON($result);
    }

    public function redoTradeLedger($campaignId, $transactionId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        $result = $this->tradeLedgerService->redo((int) $campaignId, (int) $transactionId, (array) $auth, $payload);
        if (!($result['ok'] ?? false)) {
            return $this->respondDomainError($result);
        }

        return $this->response->setJSON($result);
    }

    public function correctTradeLedger($campaignId, $transactionId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        $result = $this->tradeLedgerService->correct((int) $campaignId, (int) $transactionId, (array) $auth, $payload);
        if (!($result['ok'] ?? false)) {
            return $this->respondDomainError($result);
        }

        return $this->response->setJSON($result);
    }

    public function replaceAssortment($campaignId, $shopId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        $entries = array_values(array_filter(
            (array) ($payload['shopEntries'] ?? []),
            static fn (array $entry): bool => empty($entry['INSTANCE_META'])
                && empty($entry['IS_ITEM_INSTANCE'])
                && empty($entry['INSTANCE_IDS'])
        ));
        $ownerCode = strtoupper((string) ($payload['ownerCode'] ?? 'BG1'));

        $containers = $this->containerService->ensureBaseContainers((int) $campaignId, $ownerCode);
        $shopContainerId = (int) ($containers['SHOP_BY_ID'][(int) $shopId] ?? 0);
        if (!$shopContainerId) {
            return $this->failNotFound('Shop container not found.');
        }

        $db = \Config\Database::connect();
        $db->transBegin();
        $this->replaceTemplateContainerRows((int) $campaignId, $shopContainerId, $entries);

        if (array_key_exists('inventoryItems', $payload)) {
            $defaultEntries = array_values(array_filter((array) $payload['inventoryItems'], function ($entry): bool {
                return strtoupper((string) ($entry['OWNER_OPT'] ?? 'DEFAULT')) === 'DEFAULT'
                    && strtoupper((string) ($entry['OWNER'] ?? 'DEFAULT')) === 'DEFAULT'
                    && empty($entry['INSTANCE_META'])
                    && empty($entry['IS_ITEM_INSTANCE']);
            }));
            $defaultContainerId = (int) ($containers['DEFAULT'] ?? 0);
            if ($defaultContainerId) {
                $this->replaceTemplateContainerRows((int) $campaignId, $defaultContainerId, $defaultEntries);
            }
        }

        if (array_key_exists('trashItems', $payload)) {
            $trashContainerId = (int) (($containers['OWNER_TRASH'] ?? 0) ?: ($containers['TRASH'] ?? 0));
            if ($trashContainerId) {
                $trashEntries = array_values(array_filter(
                    (array) $payload['trashItems'],
                    static fn (array $entry): bool => strtoupper((string) ($entry['TRASH_KIND'] ?? 'TEMPLATE')) === 'TEMPLATE'
                        && empty($entry['INSTANCE_META'])
                        && empty($entry['IS_ITEM_INSTANCE'])
                ));
                $this->replaceTemplateContainerRows((int) $campaignId, $trashContainerId, $trashEntries);
            }
        }

        if (!$db->transStatus()) {
            $db->transRollback();
            return $this->respondDomainError(['code' => 'transaction_failed', 'status' => 500]);
        }
        $db->transCommit();

        return $this->response->setJSON([
            'ok' => true,
            'containerState' => $this->containerService->getContainers((int) $campaignId, $ownerCode),
        ]);
    }

    public function transferAssortment($campaignId, $shopId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        $moves = isset($payload['moves']) && is_array($payload['moves'])
            ? array_values($payload['moves'])
            : [$payload];
        $ownerCode = strtoupper((string) ($payload['ownerCode'] ?? 'BG1'));
        $moves = array_map(static function (array $move) use ($ownerCode): array {
            $move['ownerCode'] = strtoupper((string) ($move['ownerCode'] ?? $ownerCode));
            return $move;
        }, $moves);
        $containerMap = $this->containerService->ensureBaseContainers(
            (int) $campaignId,
            $ownerCode
        );
        $shopContainerId = (int) ($containerMap['SHOP_BY_ID'][(int) $shopId] ?? 0);
        foreach ($moves as $move) {
            if (
                (int) ($move['fromContainerId'] ?? 0) !== $shopContainerId
                && (int) ($move['toContainerId'] ?? 0) !== $shopContainerId
            ) {
                return $this->respondDomainError([
                    'code' => 'shop_container_mismatch',
                    'status' => 403,
                ]);
            }
        }
        $result = $this->containerService->moveMany((int) $campaignId, $moves);

        if (!$result['ok']) {
            return $this->respondDomainError($result);
        }

        return $this->response->setJSON($result);
    }

    public function generateSuggestions($campaignId, $shopId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $result = $this->suggestionService->generate((int) $campaignId, (int) $shopId);
        return $this->response->setJSON($result);
    }

    public function getSuggestions($campaignId, $shopId)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }
        $result = $this->suggestionService->getCached((int) $campaignId, (int) $shopId);
        return $this->response->setJSON($result);
    }

    public function promoteSuggestions($campaignId, $shopId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        $count = (int) ($payload['count'] ?? 30);

        $result = $this->suggestionService->promote((int) $campaignId, (int) $shopId, $count);
        return $this->response->setJSON($result);
    }

    public function applySuggestions($campaignId, $shopId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        $result = $this->suggestionService->apply((int) $campaignId, (int) $shopId, $payload);
        if (isset($result['ok']) && !$result['ok']) {
            return $this->respondDomainError($result);
        }
        return $this->response->setJSON($result);
    }
}
