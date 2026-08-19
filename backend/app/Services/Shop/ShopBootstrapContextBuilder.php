<?php

namespace App\Services\Shop;

use App\Models\CharacterModel;
use App\Models\ShopOwnerClaimModel;
use App\Services\CharacterAssetService;

final class ShopBootstrapContextBuilder
{
    private $characterModel;
    private $ownerClaimModel;
    private $mapper;
    private $characterAssetService;

    public function __construct()
    {
        $this->characterModel = new CharacterModel();
        $this->ownerClaimModel = new ShopOwnerClaimModel();
        $this->mapper = new ShopLegacyMapper();
        $this->characterAssetService = new CharacterAssetService();
    }

    public function access(int $campaignId, array $authContext): array
    {
        $role = strtolower((string) ($authContext['role'] ?? ''));
        $isGm = in_array($role, ['gm', 'admin'], true);
        $userId = isset($authContext['user_id']) ? (int) $authContext['user_id'] : null;
        $developmentAccess = !empty($authContext['development_access']);
        $selectedOwnerCodes = array_map('strtoupper', (array) ($authContext['selected_owner_codes'] ?? []));
        $selectedCharacterId = (int) ($authContext['character_id'] ?? 0);
        $claims = $this->ownerClaimModel
            ->where('campaign_id', $campaignId)
            ->orderBy('owner_code', 'ASC')
            ->findAll();
        $visibleClaims = $isGm ? $claims : array_values(array_filter(
            $claims,
            static function (array $claim) use ($userId, $developmentAccess, $selectedOwnerCodes): bool {
                if ($developmentAccess) {
                    return in_array(strtoupper((string) $claim['owner_code']), $selectedOwnerCodes, true);
                }
                return $userId && (int) $claim['user_id'] === $userId;
            }
        ));
        $claimsByCharacterId = [];
        foreach ($visibleClaims as $claim) {
            if (!empty($claim['character_id'])) {
                $claimsByCharacterId[(int) $claim['character_id']] = $claim;
            }
        }
        $characters = $this->characterModel
            ->groupStart()
                ->where('campaign_id', $campaignId)
                ->orWhere('campaign_id', null)
            ->groupEnd()
            ->orderBy('name', 'ASC')
            ->findAll();
        $characters = $this->characterAssetService->hydrateCharacters($characters);
        $actors = [];
        foreach ($characters as $character) {
            $claim = $claimsByCharacterId[(int) $character['id']] ?? null;
            if ($developmentAccess && !$isGm && $selectedCharacterId > 0 && (int) $character['id'] !== $selectedCharacterId) {
                continue;
            }
            if (!$developmentAccess && !$isGm && !$claim && (int) ($character['user_id'] ?? 0) !== (int) $userId) {
                continue;
            }
            $fallbackOwnerCode = 'CHAR_' . (int) $character['id'];
            if (
                $developmentAccess
                && $selectedCharacterId > 0
                && (int) $character['id'] === $selectedCharacterId
                && !empty($selectedOwnerCodes[0])
            ) {
                $fallbackOwnerCode = $selectedOwnerCodes[0];
            }
            $actors[] = $this->characterActor($character, $claim, $fallbackOwnerCode);
        }
        foreach ($visibleClaims as $claim) {
            if (empty($claim['character_id'])) {
                $actors[] = $this->claimActor($claim);
            }
        }
        if ($developmentAccess && !$isGm && !$actors && !empty($selectedOwnerCodes[0])) {
            $actors[] = [
                'id' => 'owner:' . $selectedOwnerCodes[0],
                'characterId' => $selectedCharacterId ?: null,
                'userId' => null,
                'ownerCode' => $selectedOwnerCodes[0],
                'name' => $selectedOwnerCodes[0],
                'avatar' => '',
                'avatarUrl' => '',
                'brass' => 0,
                'data' => [],
            ];
        }
        $permissionOwnerCodes = array_values(array_unique(array_merge(
            $selectedOwnerCodes,
            array_map(static fn (array $claim): string => strtoupper((string) $claim['owner_code']), $visibleClaims)
        )));
        return [
            'role' => $role,
            'userId' => $userId,
            'visibleClaims' => $visibleClaims,
            'actors' => $actors,
            'permissions' => $this->permissions($isGm, $permissionOwnerCodes),
        ];
    }

    public function allItemInstances(
        array $placements,
        array $instanceById,
        array $templateById,
        array $containers,
        array $actors,
        array $shops,
        string $fallbackOwnerCode
    ): array {
        $containerById = $this->indexById($containers);
        $actorNameByOwnerCode = [];
        foreach ($actors as $actor) {
            if (!empty($actor['ownerCode'])) {
                $actorNameByOwnerCode[strtoupper((string) $actor['ownerCode'])] = (string) $actor['name'];
            }
        }
        $shopNameById = [];
        foreach ($shops as $shop) {
            $shopNameById[(int) $shop['id']] = (string) $shop['name'];
        }
        $result = [];
        foreach ($placements as $placement) {
            $instance = $instanceById[(int) $placement['instance_id']] ?? null;
            $template = $instance ? ($templateById[(int) $instance['template_id']] ?? null) : null;
            $container = $containerById[(int) $placement['container_id']] ?? null;
            if (!$instance || !$template || !$container) {
                continue;
            }
            $result[] = $this->mapPlacement(
                $placement,
                $instance,
                $template,
                $container,
                $actorNameByOwnerCode,
                $shopNameById,
                $fallbackOwnerCode
            );
        }
        return $result;
    }

    public function characterIdForOwner(array $claims, string $ownerCode): ?int
    {
        foreach ($claims as $claim) {
            if (strtoupper((string) $claim['owner_code']) === strtoupper($ownerCode) && !empty($claim['character_id'])) {
                return (int) $claim['character_id'];
            }
        }
        return null;
    }

    private function mapPlacement(array $placement, array $instance, array $template, array $container, array $actorNames, array $shopNames, string $fallbackOwnerCode): array
    {
        $containerType = strtoupper((string) ($container['container_type'] ?? 'SYSTEM'));
        $systemKey = strtoupper((string) ($container['system_key'] ?? ''));
        $locationOwnerCode = strtoupper((string) ($container['owner_code'] ?? ''));
        $locationKind = $this->locationKind($containerType, $systemKey);
        $ownerOpt = $locationKind === 'CHARACTER' ? $locationOwnerCode : ($locationKind === 'TRASH' ? 'TRASH' : 'DEFAULT');
        $itemPlace = $locationKind === 'SHOP' ? 'STOISKO' : ($locationKind === 'CHARACTER' ? 'PLECY' : ($locationKind === 'TRASH' ? 'STOS' : 'DEFAULT'));
        $mapped = $this->mapper->inventoryFromInstanceRow(
            $placement,
            $instance,
            $template,
            $ownerOpt,
            $locationOwnerCode ?: $fallbackOwnerCode,
            $itemPlace
        );
        return array_merge($mapped, [
            'PLACEMENT_ID' => (int) $placement['id'],
            'CONTAINER_ID' => (int) $container['id'],
            'CONTAINER_NAME' => (string) ($container['name'] ?? ''),
            'CONTAINER_TYPE' => $containerType,
            'CONTAINER_SYSTEM_KEY' => $systemKey ?: null,
            'LOCATION_KIND' => $locationKind,
            'LOCATION_OWNER_CODE' => $locationOwnerCode ?: null,
            'LOCATION_OWNER_NAME' => $locationOwnerCode ? ($actorNames[$locationOwnerCode] ?? $locationOwnerCode) : null,
            'LOCATION_SHOP_ID' => isset($container['shop_id']) ? (int) $container['shop_id'] : null,
            'LOCATION_SHOP_NAME' => !empty($container['shop_id']) ? ($shopNames[(int) $container['shop_id']] ?? null) : null,
        ]);
    }

    private function locationKind(string $type, string $systemKey): string
    {
        if ($systemKey === 'DEFAULT') return 'UNASSIGNED';
        if ($type === 'CHARACTER') return 'CHARACTER';
        if ($type === 'SHOP') return 'SHOP';
        return $type === 'TRASH' || $systemKey === 'TRASH' ? 'TRASH' : 'SYSTEM';
    }

    private function characterActor(array $character, ?array $claim, string $fallbackOwnerCode = ''): array
    {
        $ownerCode = strtoupper((string) ($claim['owner_code'] ?? $fallbackOwnerCode));
        $avatarAsset = (array) ($character['assets']['avatar'] ?? []);
        $avatar = (string) ($avatarAsset['publicId'] ?? $character['avatar'] ?? $character['avatar_url'] ?? '');
        return [
            'id' => (int) $character['id'],
            'characterId' => (int) $character['id'],
            'userId' => isset($character['user_id']) ? (int) $character['user_id'] : null,
            'ownerCode' => $ownerCode ?: null,
            'name' => (string) ($character['name'] ?? ''),
            'avatar' => $avatar,
            'avatarUrl' => (string) ($avatarAsset['url'] ?? ''),
            'assetSetId' => isset($character['assetSetId']) ? (int) $character['assetSetId'] : null,
            'assets' => (array) ($character['assets'] ?? []),
            'brass' => max(0, (int) ($character['brass'] ?? 0)),
            'data' => (array) ($character['data'] ?? []),
        ];
    }

    private function claimActor(array $claim): array
    {
        $ownerCode = strtoupper((string) $claim['owner_code']);
        return ['id' => 'owner:'.$ownerCode, 'characterId' => null, 'userId' => (int) $claim['user_id'], 'ownerCode' => $ownerCode, 'name' => $ownerCode, 'avatar' => '', 'avatarUrl' => '', 'brass' => 0, 'data' => []];
    }

    private function permissions(bool $isGm, array $ownerCodes): array
    {
        return ['isGm' => $isGm, 'canManageShops' => $isGm, 'canManageCatalog' => $isGm, 'canManageContainers' => $isGm, 'canViewFullLedger' => $isGm, 'canTrade' => $isGm || !empty($ownerCodes), 'ownerCodes' => array_values(array_unique($ownerCodes))];
    }

    private function indexById(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) $indexed[(int) $row['id']] = $row;
        return $indexed;
    }
}
