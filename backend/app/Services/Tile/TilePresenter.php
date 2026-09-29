<?php

namespace App\Services\Tile;

final class TilePresenter
{
    public static function present(array $row, bool $canManage): array
    {
        return [
            'id' => (int) $row['id'],
            'sceneId' => (int) $row['scene_id'],
            'name' => (string) $row['name'],
            'assetUrl' => (string) $row['asset_url'],
            'mediaAssetId' => isset($row['media_asset_id']) ? (int) $row['media_asset_id'] : null,
            'mediaType' => (string) $row['media_type'],
            'layer' => (string) $row['layer'],
            'x' => (float) $row['x'],
            'y' => (float) $row['y'],
            'width' => (float) $row['width'],
            'height' => (float) $row['height'],
            'rotation' => (float) $row['rotation'],
            'opacity' => (float) $row['opacity'],
            'sortOrder' => (int) $row['sort_order'],
            'hidden' => (bool) $row['hidden'],
            'locked' => (bool) $row['locked'],
            'autoplay' => (bool) $row['autoplay'],
            'loop' => (bool) $row['loop'],
            'muted' => (bool) $row['muted'],
            'revision' => (int) $row['revision'],
            'capabilities' => ['canManage' => $canManage],
        ];
    }
}
