<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\CharacterAssetService;
use App\Models\ShopModel;
use App\Models\ShopTypeModel;
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
use App\Services\Shop\ShopItemIconResolver;
use App\Services\Shop\ShopIconMetadataService;
use App\Services\Shop\ShopPricingService;
use App\Services\Shop\ShopProfileService;
use App\Services\Shop\ShopSuggestionService;
use App\Services\Shop\ShopTradeLedgerService;
use App\Services\Shop\ShopTradeService;

trait ShopModuleControllerPart1
{
    public function __construct()
    {
        $this->authContextService = new AuthContextService();
        $this->authorizationService = new ShopAuthorizationService();
        $this->bootstrapService = new ShopBootstrapService();
        $this->catalogService = new ShopCatalogService();
        $this->containerService = new ShopContainerService();
        $this->currencyService = new ShopCurrencyService();
        $this->profileService = new ShopProfileService();
        $this->suggestionService = new ShopSuggestionService();
        $this->tradeLedgerService = new ShopTradeLedgerService();
        $this->tradeService = new ShopTradeService();
        $this->mapper = new ShopLegacyMapper();
        $this->shopModel = new ShopModel();
        $this->shopTypeModel = new ShopTypeModel();
        $this->templateModel = new ShopTemplateModel();
        $this->containerModel = new ShopContainerModel();
        $this->containerTemplateItemModel = new ShopContainerTemplateItemModel();
        $this->containerInstanceItemModel = new ShopContainerInstanceItemModel();
        $this->itemInstanceModel = new ShopItemInstanceModel();
        $this->itemIconResolver = new ShopItemIconResolver();
        $this->iconMetadataService = new ShopIconMetadataService();
        $this->characterAssetService = new CharacterAssetService();
    }

    public function bootstrap($campaignId)
    {
        $auth = $this->resolveAuth();
        $ownerCode = $this->authorizationService->resolveOwnerCode(
            (array) $auth,
            (int) $campaignId,
            (string) ($this->request->getGet('ownerCode') ?? '')
        );
        $ownerCheck = $this->authorizationService->assertOwnerAccess((array) $auth, (int) $campaignId, $ownerCode);
        if (!$ownerCheck['ok']) {
            return $this->respondError($ownerCheck);
        }

        $data = $this->bootstrapService->build((int) $campaignId, $ownerCode, (array) $auth);

        return $this->response->setJSON($data);
    }

    public function accessOptions($campaignId)
    {
        $campaignId = (int) $campaignId;
        $auth = $this->resolveAuth();
        $developmentEnabled = $this->authContextService->isDevelopmentSelectorEnabled();
        $campaignAccess = $this->authorizationService->assertCampaignAccess($auth, $campaignId);
        if (!$developmentEnabled && !$campaignAccess['ok']) {
            return $this->respondError($campaignAccess);
        }

        $db = \Config\Database::connect();
        $claims = $db->table('shop_owner_claims')
            ->where('campaign_id', $campaignId)
            ->orderBy('owner_code', 'ASC')
            ->get()
            ->getResultArray();
        $characterBuilder = $db->table('characters')
            ->groupStart()
                ->where('campaign_id', $campaignId)
                ->orWhere('campaign_id', null)
            ->groupEnd()
            ->orderBy('name', 'ASC');
        $characters = $characterBuilder->get()->getResultArray();
        $characters = $this->characterAssetService->hydrateCharacters($characters);

        $role = strtolower((string) ($auth['role'] ?? ''));
        $isPrivileged = $developmentEnabled || in_array($role, ['gm', 'admin'], true);
        $userId = (int) ($auth['user_id'] ?? 0);
        if (!$isPrivileged) {
            $claims = array_values(array_filter($claims, static function (array $claim) use ($userId): bool {
                return (int) ($claim['user_id'] ?? 0) === $userId;
            }));
            $allowedCharacterIds = array_map(static function (array $claim): int {
                return (int) ($claim['character_id'] ?? 0);
            }, $claims);
            $characters = array_values(array_filter($characters, static function (array $character) use ($allowedCharacterIds): bool {
                return in_array((int) $character['id'], $allowedCharacterIds, true);
            }));
        }

        $claimsByCharacterId = [];
        foreach ($claims as $claim) {
            if (!empty($claim['character_id'])) {
                $claimsByCharacterId[(int) $claim['character_id']] = $claim;
            }
        }
        $relevantUserIds = array_values(array_unique(array_filter(array_merge(
            array_map(static fn (array $claim): int => (int) ($claim['user_id'] ?? 0), $claims),
            array_map(static fn (array $character): int => (int) ($character['user_id'] ?? 0), $characters)
        ))));
        $userNames = [];
        $userRoles = [];
        if ($relevantUserIds) {
            foreach ($db->table('users')->select('id, username, role')->whereIn('id', $relevantUserIds)->get()->getResultArray() as $user) {
                $userNames[(int) $user['id']] = (string) ($user['username'] ?? ('User ' . $user['id']));
                $userRoles[(int) $user['id']] = strtolower((string) ($user['role'] ?? 'user'));
            }
        }
        $options = [];
        $playerGroups = [];
        $addToPlayer = static function (string $key, string $name, array $option) use (&$playerGroups): void {
            if (!isset($playerGroups[$key])) {
                $playerGroups[$key] = ['id' => $key, 'name' => $name, 'characters' => []];
            }
            $playerGroups[$key]['characters'][] = $option;
        };
        foreach ($claims as $claim) {
            if (!empty($claim['character_id'])) {
                continue;
            }
            $ownerCode = strtoupper((string) $claim['owner_code']);
            $option = [
                'characterId' => null,
                'ownerCode' => $ownerCode,
                'name' => $ownerCode,
                'avatar' => '',
                'avatarUrl' => '',
                'brass' => 0,
                'primaryCurrencyCode' => null,
            ];
            $options[] = $option;
            $claimUserId = (int) ($claim['user_id'] ?? 0);
            if (in_array($userRoles[$claimUserId] ?? 'user', ['gm', 'admin'], true)) {
                continue;
            }
            $addToPlayer(
                'user:' . $claimUserId,
                $userNames[$claimUserId] ?? ('User ' . $claimUserId),
                $option
            );
        }
        foreach ($characters as $character) {
            $claim = $claimsByCharacterId[(int) $character['id']] ?? null;
            $avatarAsset = (array) ($character['assets']['avatar'] ?? []);
            $avatar = (string) ($avatarAsset['publicId'] ?? $character['avatar'] ?? $character['avatar_url'] ?? '');
            $option = [
                'characterId' => (int) $character['id'],
                'ownerCode' => strtoupper((string) ($claim['owner_code'] ?? ('CHAR_' . $character['id']))),
                'name' => (string) ($character['name'] ?? ('Character ' . $character['id'])),
                'avatar' => $avatar,
                'avatarUrl' => (string) ($avatarAsset['url'] ?? ''),
                'assetSetId' => isset($character['assetSetId']) ? (int) $character['assetSetId'] : null,
                'assets' => (array) ($character['assets'] ?? []),
                'brass' => max(0, (int) ($character['brass'] ?? 0)),
                'primaryCurrencyCode' => strtolower((string) ($character['primary_currency_code'] ?? '')),
            ];
            $options[] = $option;
            $characterUserId = (int) (($claim['user_id'] ?? null) ?: ($character['user_id'] ?? 0));
            $characterData = $character['data'] ?? [];
            if (is_string($characterData)) {
                $characterData = json_decode($characterData, true) ?: [];
            }
            $gamerName = trim((string) ($characterData['meta']['gamer_name'] ?? ''));
            $normalizedGamerName = strtolower($gamerName);
            if (
                in_array($userRoles[$characterUserId] ?? 'user', ['gm', 'admin'], true)
                || ($characterUserId <= 0 && in_array($normalizedGamerName, ['gm', 'game master', 'mistrz gry'], true))
            ) {
                continue;
            }
            $playerKey = $characterUserId > 0
                ? 'user:' . $characterUserId
                : ($gamerName !== '' ? 'demo:' . substr(sha1($gamerName), 0, 12) : 'character:' . $character['id']);
            $playerName = $characterUserId > 0
                ? ($userNames[$characterUserId] ?? ('User ' . $characterUserId))
                : ($gamerName ?: (string) $option['name']);
            $addToPlayer($playerKey, $playerName, $option);
        }
        uasort($playerGroups, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));
        $players = [];
        foreach (array_values($playerGroups) as $index => $player) {
            $player['number'] = $index + 1;
            $players[] = $player;
        }

        $gmAccess = $this->authorizationService->assertGm($auth, $campaignId);
        return $this->response->setJSON([
            'developmentSelectorEnabled' => $developmentEnabled,
            'campaignId' => $campaignId,
            'modes' => [
                'gm' => $developmentEnabled || $gmAccess['ok'],
                'player' => !empty($options),
            ],
            'characters' => $options,
            'players' => $players,
        ]);
    }

    public function getCatalog($campaignId)
    {
        return $this->response->setJSON($this->catalogService->getCatalogNetwork());
    }

    public function getItemDictionaries($campaignId)
    {
        return $this->response->setJSON($this->catalogService->getItemDictionaries((int) $campaignId));
    }

    public function createItemDictionaryEntry($campaignId)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }
        $saved = $this->catalogService->saveItemDictionaryEntry(
            (int) $campaignId,
            $this->request->getJSON(true) ?: []
        );
        return $saved
            ? $this->response->setStatusCode(201)->setJSON(['ok' => true, 'entry' => $saved, 'dictionaries' => $this->catalogService->getItemDictionaries((int) $campaignId)])
            : $this->fail(['code' => 'invalid_dictionary_entry'], 400);
    }

    public function updateItemDictionaryEntry($campaignId, $entryId)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }
        $saved = $this->catalogService->saveItemDictionaryEntry(
            (int) $campaignId,
            $this->request->getJSON(true) ?: [],
            (int) $entryId
        );
        return $saved
            ? $this->response->setJSON(['ok' => true, 'entry' => $saved, 'dictionaries' => $this->catalogService->getItemDictionaries((int) $campaignId)])
            : $this->failNotFound('Dictionary entry not found.');
    }

    public function deleteItemDictionaryEntry($campaignId, $entryId)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }
        if (!$this->catalogService->archiveItemDictionaryEntry((int) $campaignId, (int) $entryId)) {
            return $this->failNotFound('Dictionary entry not found.');
        }
        return $this->response->setJSON(['ok' => true, 'dictionaries' => $this->catalogService->getItemDictionaries((int) $campaignId)]);
    }

    public function getCurrencies($campaignId)
    {
        return $this->response->setJSON(
            $this->currencyService->getCampaignCurrencyContext((int) $campaignId)
        );
    }

    public function getIconMetadata($campaignId)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }
        return $this->response->setJSON([
            'metadata' => $this->iconMetadataService->listForCampaign((int) $campaignId),
        ]);
    }

    public function putIconMetadata($campaignId, $iconClass)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }
        $saved = $this->iconMetadataService->save(
            (int) $campaignId,
            (string) $iconClass,
            $this->request->getJSON(true) ?: []
        );
        return $saved
            ? $this->response->setJSON(['ok' => true, 'metadata' => $saved])
            : $this->fail(['code' => 'invalid_icon_metadata'], 400);
    }

    public function uploadIcon($campaignId)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }
        $smallFile = $this->request->getFile('iconSmall');
        $largeFile = $this->request->getFile('iconLarge');
        $saved = $smallFile && $largeFile
            ? $this->iconMetadataService->createFromUploads((int) $campaignId, $smallFile, $largeFile)
            : $this->iconMetadataService->createFromUpload((int) $campaignId, $this->request->getFile('icon'));
        return $saved
            ? $this->response->setStatusCode(201)->setJSON(['ok' => true, 'metadata' => $saved])
            : $this->fail(['code' => 'invalid_icon_file'], 400);
    }

    public function replaceIconImages($campaignId, $iconClass)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }
        $saved = $this->iconMetadataService->replaceImages(
            (int) $campaignId,
            (string) $iconClass,
            $this->request->getFile('iconSmall'),
            $this->request->getFile('iconLarge'),
            [
                'name' => $this->request->getPost('name'),
                'sourceName' => $this->request->getPost('sourceName'),
                'description' => $this->request->getPost('description'),
                'specialMarks' => $this->request->getPost('specialMarks'),
                'typeKeys' => $this->request->getPost('typeKeys') ?: [],
                'subtypeKeys' => $this->request->getPost('subtypeKeys') ?: [],
                'itemClasses' => $this->request->getPost('itemClasses') ?: [],
                'itemGenres' => $this->request->getPost('itemGenres') ?: [],
            ]
        );
        return $saved
            ? $this->response->setJSON(['ok' => true, 'metadata' => $saved])
            : $this->fail(['code' => 'invalid_icon_files'], 400);
    }

    public function deleteIconMetadata($campaignId, $iconClass)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }
        $this->iconMetadataService->delete((int) $campaignId, (string) $iconClass);
        return $this->response->setJSON(['ok' => true]);
    }

    public function getWorldProfiles($campaignId)
    {
        return $this->response->setJSON($this->catalogService->getWorldProfiles());
    }

    public function listShops($campaignId)
    {
        $shops = $this->shopModel
            ->where('campaign_id', (int) $campaignId)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->findAll();

        $items = array_map(function (array $shop): array {
            return $this->mapper->shopToLegacy($shop);
        }, $shops);

        return $this->response->setJSON([
            'count' => count($items),
            'items' => $items,
        ]);
    }

    public function createShop($campaignId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $input = $this->request->getJSON(true) ?: [];
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return $this->fail(['code' => 'invalid_payload', 'message' => 'Shop name is required.'], 400);
        }

        $ownerCode = strtoupper((string) ($input['ownerCode'] ?? 'BG1'));
        $ownerName = (string) ($input['ownerName'] ?? '');
        $typeId = trim((string) ($input['typeId'] ?? ''));
        if ($typeId !== '') {
            $knownType = $this->shopTypeModel
                ->where('slug', $typeId)
                ->where('is_active', 1)
                ->first();
            if (!$knownType) {
                return $this->fail(['code' => 'invalid_shop_type', 'message' => 'Unknown shop type.'], 400);
            }
        }

        $db = \Config\Database::connect();
        $db->transBegin();
        $this->shopModel->insert([
            'campaign_id' => (int) $campaignId,
            'name' => $name,
            'owner_code' => $ownerCode,
            'owner_name' => $ownerName,
            'is_active' => 1,
        ]);

        $shopId = (int) $this->shopModel->getInsertID();
        $this->containerService->ensureBaseContainers((int) $campaignId, $ownerCode);

        $profilePayload = [
            'typeId' => $typeId,
            'signboardName' => $name,
            'ownerCode' => $ownerCode,
            'ownerName' => $ownerName,
            'signboardAltNames' => [],
            'categoryTags' => [],
            'worldProfileId' => 'standard',
            'locationType' => 'miasto',
            'legalStatus' => 'legal',
            'wealthTier' => 'standard',
            'reputation' => 'neutralna',
            'seasonality' => 'caloroczny',
            'counterfeitRisk' => 10,
            'pricingConfig' => ShopPricingService::defaultPricingConfig(),
        ];
        $profile = $this->profileService->upsertProfile(
            (int) $campaignId,
            $shopId,
            $profilePayload,
            isset($auth['user_id']) ? (int) $auth['user_id'] : null,
            'create'
        );

        if (!$shopId || !$profile || !$db->transStatus()) {
            $db->transRollback();
            return $this->respondDomainError(['code' => 'transaction_failed', 'status' => 500]);
        }
        $db->transCommit();

        $shop = $this->shopModel->find($shopId);

        return $this->response->setStatusCode(201)->setJSON([
            'message' => 'Shop created.',
            'shop' => $this->mapper->shopToLegacy($shop),
        ]);
    }

    public function showShop($campaignId, $shopId)
    {
        $shop = $this->shopModel
            ->where('campaign_id', (int) $campaignId)
            ->where('id', (int) $shopId)
            ->where('deleted_at', null)
            ->first();

        if (!$shop) {
            return $this->failNotFound('Shop not found.');
        }

        return $this->response->setJSON($this->mapper->shopToLegacy($shop));
    }

    public function updateShop($campaignId, $shopId)
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
        $updates = [];
        if (array_key_exists('name', $input)) {
            $updates['name'] = trim((string) $input['name']);
        }
        if (array_key_exists('ownerCode', $input)) {
            $updates['owner_code'] = strtoupper((string) $input['ownerCode']);
        }
        if (array_key_exists('ownerName', $input)) {
            $updates['owner_name'] = (string) $input['ownerName'];
        }

        if ($updates) {
            $this->shopModel->update((int) $shopId, $updates);
        }

        $updated = $this->shopModel->find((int) $shopId);

        return $this->response->setJSON([
            'message' => 'Shop updated.',
            'shop' => $this->mapper->shopToLegacy($updated),
        ]);
    }
}
