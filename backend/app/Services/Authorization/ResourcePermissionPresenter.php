<?php

namespace App\Services\Authorization;

final class ResourcePermissionPresenter
{
    public static function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'campaignId' => (int) $row['campaign_id'],
            'resourceType' => (string) $row['resource_type'],
            'resourceId' => (int) $row['resource_id'],
            'user' => [
                'id' => (int) $row['user_id'],
                'username' => (string) ($row['username'] ?? ''),
                'avatarUrl' => $row['avatar_url'] ?? null,
            ],
            'accessLevel' => (string) $row['access_level'],
            'grantedByUserId' => !empty($row['granted_by_user_id'])
                ? (int) $row['granted_by_user_id'] : null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }
}
