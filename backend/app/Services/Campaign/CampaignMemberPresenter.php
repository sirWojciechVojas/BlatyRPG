<?php

namespace App\Services\Campaign;

final class CampaignMemberPresenter
{
    public static function present(array $row, bool $includeEmail = false): array
    {
        $item = [
            'id' => (int) $row['id'],
            'campaignId' => (int) $row['campaign_id'],
            'userId' => (int) $row['user_id'],
            'username' => (string) ($row['username'] ?? ''),
            'avatarUrl' => $row['avatar_url'] ?? null,
            'role' => (string) ($row['role'] ?? CampaignRole::PLAYER),
            'isActive' => !empty($row['is_active']),
            'joinedAt' => $row['joined_at'] ?? $row['created_at'] ?? null,
            'leftAt' => $row['left_at'] ?? null,
        ];
        if ($includeEmail) {
            $item['email'] = (string) ($row['email'] ?? '');
        }
        return $item;
    }
}
