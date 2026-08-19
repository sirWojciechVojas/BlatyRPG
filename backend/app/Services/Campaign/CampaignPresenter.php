<?php

namespace App\Services\Campaign;

final class CampaignPresenter
{
    public static function present(array $campaign, array $context): array
    {
        return [
            'id' => (int) $campaign['id'],
            'name' => (string) $campaign['name'],
            'description' => $campaign['description'] ?? null,
            'bannerUrl' => $campaign['banner_url'] ?? null,
            'systemType' => (string) ($campaign['system_type'] ?? ''),
            'systemId' => isset($campaign['rpg_system_id'])
                ? (int) $campaign['rpg_system_id'] : null,
            'universeId' => isset($campaign['rpg_universe_id'])
                ? (int) $campaign['rpg_universe_id'] : null,
            'status' => (string) ($campaign['status'] ?? (
                !empty($campaign['is_active']) ? 'active' : 'paused'
            )),
            'isActive' => !empty($campaign['is_active']),
            'gameMasterId' => (int) $campaign['game_master_id'],
            'settings' => self::settings($campaign['settings_json'] ?? []),
            'accessRole' => (string) $context['accessRole'],
            'capabilities' => $context['capabilities'],
            'createdAt' => $campaign['created_at'] ?? null,
            'updatedAt' => $campaign['updated_at'] ?? null,
            'lastActivityAt' => $campaign['last_activity_at'] ?? null,
        ];
    }

    private static function settings($settings): array
    {
        if (is_array($settings)) {
            return $settings;
        }
        $decoded = is_string($settings) ? json_decode($settings, true) : null;
        return is_array($decoded) ? $decoded : [];
    }
}
