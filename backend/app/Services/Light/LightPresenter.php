<?php

namespace App\Services\Light;

final class LightPresenter
{
    public static function present(array $row, bool $canManage): array
    {
        return [
            'id' => (int) $row['id'],
            'sceneId' => (int) $row['scene_id'],
            'x' => (float) $row['x'],
            'y' => (float) $row['y'],
            'brightRadius' => (float) $row['bright_radius'],
            'dimRadius' => (float) $row['dim_radius'],
            'color' => (string) $row['color'],
            'intensity' => (float) $row['intensity'],
            'opacity' => (float) ($row['opacity'] ?? 1),
            'softness' => (float) ($row['softness'] ?? 0.5),
            'gradualIllumination' => (bool) ($row['gradual_illumination'] ?? true),
            'darknessMin' => (float) ($row['darkness_min'] ?? 0),
            'darknessMax' => (float) ($row['darkness_max'] ?? 1),
            'sourceType' => (string) ($row['source_type'] ?? 'light'),
            'providesVision' => (bool) ($row['provides_vision'] ?? false),
            'constrainedByWalls' => (bool) ($row['constrained_by_walls'] ?? true),
            'animation' => (string) ($row['animation'] ?? 'none'),
            'animationSpeed' => (float) ($row['animation_speed'] ?? 1),
            'animationIntensity' => (float) ($row['animation_intensity'] ?? 0.5),
            'elevation' => (float) ($row['elevation'] ?? 0),
            'enabled' => (bool) $row['enabled'],
            'hidden' => (bool) $row['hidden'],
            'revision' => (int) $row['revision'],
            'capabilities' => ['canManage' => $canManage],
        ];
    }
}
