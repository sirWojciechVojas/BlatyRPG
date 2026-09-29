<?php

namespace App\Services\Campaign;

use App\Services\Auth\UserRole;

final class CampaignCreationPolicy
{
    public function allows(array $auth): bool
    {
        if ((int) ($auth['user_id'] ?? 0) < 1 || !empty($auth['anonymous'])) {
            return false;
        }

        return UserRole::isSupported($auth['role'] ?? '');
    }
}
