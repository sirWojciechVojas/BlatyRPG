<?php

namespace App\Services\Character;

use App\Services\Authorization\AccessLevel;

/** Pure character policy. Database lookups stay in CharacterDirectoryService. */
final class CharacterAccessPolicy
{
    public function campaign(array $auth, array $campaign, ?array $membership): array
    {
        $denied = ['canAccess' => false, 'canManageAll' => false];
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) {
            return $denied;
        }
        if (strtolower((string) ($auth['role'] ?? '')) === 'admin') {
            return ['canAccess' => true, 'canManageAll' => true];
        }
        if ((int) ($campaign['game_master_id'] ?? 0) === $userId) {
            return ['canAccess' => true, 'canManageAll' => true];
        }
        if (!$membership || empty($membership['is_active'])) {
            return $denied;
        }
        if ((int) ($membership['user_id'] ?? 0) !== $userId) {
            return $denied;
        }

        $role = strtolower((string) ($membership['role'] ?? 'player'));
        if (!in_array($role, ['gm', 'assistant', 'player', 'observer'], true)) {
            return $denied;
        }
        $permissions = $this->permissions($membership['permissions_json'] ?? []);
        return [
            'canAccess' => $this->permission($permissions, 'view_characters', true),
            'canManageAll' => $this->permission($permissions, 'manage_characters', $role === 'gm'),
        ];
    }

    public function character(
        array $campaignAccess,
        array $character,
        int $userId,
        int $campaignId,
        $grantedLevel
    ): array {
        $denied = ['canView' => false, 'canEdit' => false, 'canDelete' => false];
        if (empty($campaignAccess['canAccess'])) {
            return $denied;
        }
        $characterCampaignId = (int) ($character['campaign_id'] ?? 0);
        $isUnassigned = $characterCampaignId < 1;
        if (!$isUnassigned && $characterCampaignId !== $campaignId) {
            return $denied;
        }

        $manages = !empty($campaignAccess['canManageAll']);
        $primaryOwner = $userId > 0 && (int) ($character['user_id'] ?? 0) === $userId;
        $explicit = is_bool($grantedLevel)
            ? ($grantedLevel ? AccessLevel::OWNER : null)
            : AccessLevel::normalize($grantedLevel);
        $level = $primaryOwner ? AccessLevel::OWNER : $explicit;
        if ($level === null) {
            $level = AccessLevel::normalize($character['visibility_level'] ?? null)
                ?: AccessLevel::NONE;
        }
        // Legacy unassigned records remain visible to campaign managers, but
        // only a database administrator may mutate globally shared data.
        $isAdmin = !empty($campaignAccess['isAdmin']);
        if ($manages && (!$isUnassigned || $isAdmin)) {
            $level = AccessLevel::OWNER;
        } elseif ($manages && $isUnassigned) {
            $level = AccessLevel::OBSERVER;
        }
        if (!AccessLevel::allows($level, AccessLevel::LIMITED)) {
            return $denied;
        }
        $canEdit = AccessLevel::allows($level, AccessLevel::OWNER)
            && (!$isUnassigned || $isAdmin);
        return [
            'canView' => true,
            'canObserve' => AccessLevel::allows($level, AccessLevel::OBSERVER),
            'canEdit' => $canEdit,
            'canDelete' => $manages && (!$isUnassigned || $isAdmin),
            'accessLevel' => $level,
        ];
    }

    private function permissions($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = is_string($value) ? json_decode($value, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    private function permission(array $permissions, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $permissions)) {
            return $default;
        }
        return in_array($permissions[$key], [true, 1, '1'], true);
    }
}
