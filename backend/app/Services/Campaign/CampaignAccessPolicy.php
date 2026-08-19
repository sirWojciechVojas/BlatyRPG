<?php

namespace App\Services\Campaign;

/** Pure authorization policy; database lookup stays in CampaignAccessService. */
class CampaignAccessPolicy
{
    public function evaluate(array $auth, array $campaign, ?array $membership): array
    {
        $denied = ['canAccess' => false, 'canManage' => false, 'canViewHidden' => false];
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) {
            return $denied;
        }

        if (strtolower((string) ($auth['role'] ?? '')) === 'admin') {
            return ['canAccess' => true, 'canManage' => true, 'canViewHidden' => true];
        }
        if ((int) ($campaign['game_master_id'] ?? 0) === $userId) {
            return ['canAccess' => true, 'canManage' => true, 'canViewHidden' => true];
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
        $manageDefault = $role === 'gm';
        $hiddenDefault = in_array($role, ['gm', 'assistant'], true);

        return [
            'canAccess' => true,
            'canManage' => $this->permission($permissions, 'manage_scenes', $manageDefault),
            'canViewHidden' => $this->permission($permissions, 'view_hidden_scenes', $hiddenDefault),
        ];
    }

    private function permissions($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function permission(array $permissions, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $permissions)) {
            return $default;
        }
        $value = $permissions[$key];
        return $value === true || $value === 1 || $value === '1';
    }
}
