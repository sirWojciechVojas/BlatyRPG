<?php

namespace App\Services\Campaign;

final class CampaignCreationPolicy
{
    public function allows(array $auth): bool
    {
        if ((int) ($auth['user_id'] ?? 0) < 1 || !empty($auth['anonymous'])) {
            return false;
        }

        return in_array(
            strtolower((string) ($auth['role'] ?? '')),
            ['player', 'gm', 'admin'],
            true
        );
    }
}
