<?php

namespace App\Services\Token;

final class TokenTemplatePresenter
{
    public static function present(array $row, string $imageUrl): array
    {
        $assetId = isset($row['image_asset_id']) ? (int) $row['image_asset_id'] : null;
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'imageUrl' => $imageUrl,
            'imageAssetId' => $assetId,
            'widthCells' => (float) ($row['width_cells'] ?? 1),
            'heightCells' => (float) ($row['height_cells'] ?? 1),
            'rotation' => (float) ($row['rotation'] ?? 0),
            'facing' => (float) ($row['facing'] ?? $row['rotation'] ?? 0),
            'rotationHandleEnabled' => !empty($row['rotation_handle_enabled']),
            'facingHandleEnabled' => !empty($row['facing_handle_enabled']),
            'rotationFollowsFacing' => !empty($row['rotation_follows_facing']),
            'showInfoUnselected' => !empty($row['show_info_unselected']),
            'resourceBarPosition' => (string) ($row['resource_bar_position'] ?? 'below'),
            'elevation' => (float) ($row['elevation'] ?? 0),
            'disposition' => (string) ($row['disposition'] ?? 'neutral'),
            'movementRange' => (float) ($row['movement_range'] ?? 6),
            'movementResetMode' => (string) ($row['movement_reset_mode'] ?? 'turn'),
            'resources' => TokenResourceValidator::stored($row['bars_json'] ?? []),
            'vision' => self::vision($row['vision_json'] ?? []),
            'revision' => (int) ($row['revision'] ?? 1),
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    private static function vision($value): array
    {
        $validated = TokenVisionValidator::validate(is_array($value) ? $value : []);
        return $validated['valid'] ? $validated['data'] : TokenVisionValidator::validate([])['data'];
    }
}
