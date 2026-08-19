<?php

namespace App\Services\Chat;

use App\Services\Campaign\CampaignAccessPolicy;

/** Adds chat-specific permissions on top of the shared campaign policy. */
class CampaignChatAccessPolicy
{
    private $campaignPolicy;

    public function __construct(?CampaignAccessPolicy $campaignPolicy = null)
    {
        $this->campaignPolicy = $campaignPolicy ?: new CampaignAccessPolicy();
    }

    public function evaluate(array $auth, array $campaign, ?array $membership): array
    {
        $base = $this->campaignPolicy->evaluate($auth, $campaign, $membership);
        $denied = ['canRead' => false, 'canSend' => false, 'canModerate' => false];
        if (empty($base['canAccess'])) {
            return $denied;
        }

        $userId = (int) ($auth['user_id'] ?? 0);
        $globalRole = strtolower((string) ($auth['role'] ?? ''));
        if ($globalRole === 'admin' || (int) ($campaign['game_master_id'] ?? 0) === $userId) {
            return ['canRead' => true, 'canSend' => true, 'canModerate' => true];
        }

        $role = strtolower((string) ($membership['role'] ?? 'player'));
        $permissions = $this->permissions($membership['permissions_json'] ?? []);
        $canRead = $this->permission($permissions, 'read_chat', true);

        return [
            'canRead' => $canRead,
            'canSend' => $canRead && $this->permission($permissions, 'send_chat', $role !== 'observer'),
            'canModerate' => $canRead && $this->permission(
                $permissions,
                'moderate_chat',
                in_array($role, ['gm', 'assistant'], true)
            ),
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
        return $permissions[$key] === true || $permissions[$key] === 1
            || $permissions[$key] === '1';
    }
}
