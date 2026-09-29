<?php

namespace App\Services\Region;

final class RegionPresenter
{
    public static function present(array $row, bool $canManage): array
    {
        $polygons = $row['polygons_json'] ?? [];
        if (is_string($polygons)) $polygons = json_decode($polygons, true) ?: [];
        return [
            'id' => (int) $row['id'],
            'sceneId' => (int) $row['scene_id'],
            'name' => (string) ($row['name'] ?? 'Region'),
            'polygons' => $polygons,
            'darknessMode' => (string) ($row['darkness_mode'] ?? 'override'),
            'darknessValue' => (float) ($row['darkness_value'] ?? 0),
            'disableGlobalIllumination' => (bool) ($row['disable_global_illumination'] ?? false),
            'color' => (string) ($row['color'] ?? '#8B5CF6'),
            'enabled' => (bool) ($row['enabled'] ?? true),
            'hidden' => $canManage && (bool) ($row['hidden'] ?? false),
            'revision' => (int) $row['revision'],
            'capabilities' => ['canManage' => $canManage],
        ];
    }
}
