<?php

namespace App\Services\Token;

final class TokenPresenter
{
    public static function present(
        array $row,
        bool $canControl,
        bool $canManage,
        bool $canEdit = false,
        bool $canObserve = false
    ): array {
        $resources = TokenResourceValidator::stored($row['bars_json'] ?? []);
        $movementRange = (float) ($row['movement_range'] ?? 6);
        $movementSpent = (float) ($row['movement_spent'] ?? 0);
        $movementPoints = max(0.0, round($movementRange - $movementSpent, 3));
        $resources = TokenMovementResource::fromMovement(
            $resources,
            $movementRange,
            $movementSpent
        );
        $assetId = isset($row['token_template_asset_id'])
            ? (int) $row['token_template_asset_id'] : null;
        $campaignId = isset($row['campaign_id']) ? (int) $row['campaign_id'] : 0;
        $imageUrl = $assetId && $campaignId
            ? '/api/campaigns/' . $campaignId . '/token-template-assets/' . $assetId . '/file'
            : (string) ($row['image_url'] ?? '');
        return [
            'id' => (int) $row['id'],
            'sceneId' => (int) $row['scene_id'],
            'characterId' => isset($row['character_id']) ? (int) $row['character_id'] : null,
            'tokenTemplateId' => isset($row['token_template_id']) ? (int) $row['token_template_id'] : null,
            'tokenTemplateAssetId' => $assetId,
            'name' => (string) $row['name'],
            'imageUrl' => $imageUrl,
            'x' => (float) $row['x'],
            'y' => (float) $row['y'],
            'width' => (float) $row['width'],
            'height' => (float) $row['height'],
            'rotation' => (float) $row['rotation'],
            'facing' => (float) ($row['facing'] ?? $row['rotation']),
            'rotationHandleEnabled' => !empty($row['rotation_handle_enabled']),
            'facingHandleEnabled' => !empty($row['facing_handle_enabled']),
            'rotationFollowsFacing' => !empty($row['rotation_follows_facing']),
            'showInfoUnselected' => !empty($row['show_info_unselected']),
            'resourceBarPosition' => in_array(
                $row['resource_bar_position'] ?? 'below',
                ['above', 'top-overlap', 'bottom-overlap', 'below'],
                true
            ) ? ($row['resource_bar_position'] ?? 'below') : 'below',
            'movementRange' => $movementRange,
            'movementSpent' => $movementSpent,
            'movementPoints' => $movementPoints,
            'movementResetMode' => (string) ($row['movement_reset_mode'] ?? 'turn'),
            'elevation' => (float) $row['elevation'],
            'disposition' => (string) $row['disposition'],
            'hidden' => (bool) $row['hidden'],
            'locked' => (bool) $row['locked'],
            'visibleTo' => TokenPermissionScope::stored(
                $row['visible_to_json'] ?? null,
                !empty($row['hidden']) ? 'gm' : 'everyone'
            ),
            'controlledBy' => TokenPermissionScope::stored(
                $row['controlled_by_json'] ?? null,
                'inherit'
            ),
            'editableBy' => TokenPermissionScope::stored(
                $row['editable_by_json'] ?? null,
                'gm'
            ),
            'observerBy' => TokenPermissionScope::stored(
                $row['observer_by_json'] ?? null,
                'inherit'
            ),
            'bars' => $resources['bars'],
            'resources' => $resources,
            'statuses' => (array) ($row['statuses_json'] ?? []),
            'vision' => (array) ($row['vision_json'] ?? []),
            'light' => (array) ($row['light_json'] ?? []),
            'revision' => (int) $row['revision'],
            'capabilities' => [
                'canControl' => $canControl,
                'canEdit' => $canEdit,
                'canObserve' => $canObserve,
                'canManage' => $canManage,
            ],
        ];
    }
}
