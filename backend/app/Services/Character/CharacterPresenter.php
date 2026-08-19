<?php

namespace App\Services\Character;

use App\Services\Authorization\AccessLevel;

final class CharacterPresenter
{
    public static function present(array $row): array
    {
        $permissions = (array) ($row['_permissions'] ?? []);
        $accessLevel = AccessLevel::normalize($permissions['accessLevel'] ?? null)
            ?: AccessLevel::NONE;
        $limited = $accessLevel === AccessLevel::LIMITED
            && empty($permissions['canEdit']);
        $result = [
            'id' => (int) $row['id'],
            'campaignId' => !empty($row['campaign_id']) ? (int) $row['campaign_id'] : null,
            'ownerUserId' => !empty($row['user_id']) ? (int) $row['user_id'] : null,
            'systemId' => (int) ($row['system_id'] ?? 0),
            'universeId' => !empty($row['universe_id']) ? (int) $row['universe_id'] : null,
            'name' => (string) ($row['name'] ?? ''),
            'data' => !$limited && is_array($row['data'] ?? null) ? $row['data'] : [],
            'avatarUrl' => (string) ($row['avatar_url'] ?? $row['avatar'] ?? ''),
            'assets' => $limited ? [] : (array) ($row['assets'] ?? []),
            'assetSetId' => $limited || empty($row['asset_set_id'])
                ? null : (int) $row['asset_set_id'],
            'assetSet' => $limited ? null : ($row['assetSet'] ?? null),
            'brass' => $limited ? 0 : max(0, (int) ($row['brass'] ?? 0)),
            'primaryCurrencyCode' => $limited
                ? '' : (string) ($row['primary_currency_code'] ?? ''),
            'revision' => max(1, (int) ($row['revision'] ?? 1)),
            'visibility' => (string) ($row['visibility_level'] ?? AccessLevel::NONE),
            'accessLevel' => $accessLevel,
            'isLegacyUnassigned' => empty($row['campaign_id']),
            'capabilities' => [
                'canEdit' => !empty($permissions['canEdit']),
                'canDelete' => !empty($permissions['canDelete']),
                'canObserve' => !empty($permissions['canObserve']),
            ],
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
        return $result + [
            'campaign_id' => $result['campaignId'],
            'user_id' => $result['ownerUserId'],
            'system_id' => $result['systemId'],
            'universe_id' => $result['universeId'],
            'avatar_url' => $result['avatarUrl'],
            'asset_set_id' => $result['assetSetId'],
            'primary_currency_code' => $result['primaryCurrencyCode'],
            'visibility_level' => $result['visibility'],
            'created_at' => $result['createdAt'],
            'updated_at' => $result['updatedAt'],
        ];
    }
}
