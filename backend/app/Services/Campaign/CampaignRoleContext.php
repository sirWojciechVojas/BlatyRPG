<?php

namespace App\Services\Campaign;

use App\Services\Auth\UserRole;

/** Separates application privileges from the user's role at one campaign. */
final class CampaignRoleContext
{
    public static function resolve(
        array $auth,
        array $campaign,
        ?array $membership
    ): array {
        $userId = (int) ($auth['user_id'] ?? 0);
        $globalRole = UserRole::normalize($auth['role'] ?? '');
        $isAdmin = $globalRole === UserRole::ADMIN;
        $isOwner = $userId > 0
            && (int) ($campaign['game_master_id'] ?? 0) === $userId;
        $memberRole = self::activeMemberRole($membership, $userId);
        $campaignRole = $isOwner ? CampaignRole::GM : $memberRole;

        return [
            'globalRole' => $globalRole,
            'campaignRole' => $campaignRole,
            'accessRole' => $campaignRole ?: ($isAdmin ? UserRole::ADMIN : CampaignRole::PLAYER),
            'isAdmin' => $isAdmin,
            'isOwner' => $isOwner,
            'isGameMaster' => $campaignRole === CampaignRole::GM,
            'isPlayer' => $campaignRole === CampaignRole::PLAYER,
        ];
    }

    private static function activeMemberRole(?array $membership, int $userId): ?string
    {
        if (!$membership || empty($membership['is_active'])) {
            return null;
        }
        if ((int) ($membership['user_id'] ?? 0) !== $userId) {
            return null;
        }
        return CampaignRole::normalize($membership['role'] ?? null);
    }
}
