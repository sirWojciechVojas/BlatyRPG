<?php

namespace App\Services\Character;

use App\Models\ResourcePermissionModel;
use App\Models\ShopOwnerClaimModel;
use App\Services\Authorization\AccessLevel;

/** Bulk-loads character grants while preserving legacy shop-owner claims. */
final class CharacterPermissionResolver
{
    private $permissions;
    private $claims;

    public function __construct(
        ?ResourcePermissionModel $permissions = null,
        ?ShopOwnerClaimModel $claims = null
    ) {
        $this->permissions = $permissions ?: new ResourcePermissionModel();
        $this->claims = $claims ?: new ShopOwnerClaimModel();
    }

    public function levelsFor(int $userId, int $campaignId): array
    {
        $rows = $this->permissions->select('resource_id, access_level')
            ->where('campaign_id', $campaignId)
            ->where('resource_type', 'character')
            ->where('user_id', $userId)
            ->findAll();
        $claims = $this->claims->select('character_id')
            ->where('campaign_id', $campaignId)
            ->where('user_id', $userId)
            ->where('character_id IS NOT NULL', null, false)
            ->findAll();
        return self::levelsFromRows($rows, $claims);
    }

    /** @internal Pure merge kept public for focused authorization tests. */
    public static function levelsFromRows(array $rows, array $claims): array
    {
        $levels = [];
        foreach ($claims as $claim) {
            $characterId = (int) $claim['character_id'];
            if ($characterId > 0) {
                $levels[$characterId] = AccessLevel::OWNER;
            }
        }
        // Explicit resource permissions are authoritative, including NONE.
        foreach ($rows as $row) {
            $characterId = (int) ($row['resource_id'] ?? 0);
            if ($characterId > 0) {
                $levels[$characterId] = AccessLevel::normalize(
                    $row['access_level'] ?? null
                ) ?: AccessLevel::NONE;
            }
        }
        return $levels;
    }
}
