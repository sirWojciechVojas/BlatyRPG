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
            'name' => (string) ($row['name'] ?? 'Light'),
            'lumens' => (int) ($row['lumens'] ?? round(($row['intensity'] ?? 1) * 800)),
            'direction' => (float) ($row['direction'] ?? 0),
            'angle' => (float) ($row['angle'] ?? 90),
            'areaWidth' => (float) ($row['area_width'] ?? 400),
            'areaHeight' => (float) ($row['area_height'] ?? 400),
            'brightRadius' => (float) $row['bright_radius'],
            'dimRadius' => (float) $row['dim_radius'],
            'color' => (string) $row['color'],
            'intensity' => (float) $row['intensity'],
            'opacity' => (float) ($row['opacity'] ?? 1),
            'softness' => (float) ($row['softness'] ?? 0.5),
            'clarity' => (float) ($row['clarity'] ?? 0),
            'gradualIllumination' => (bool) ($row['gradual_illumination'] ?? true),
            'darknessMin' => (float) ($row['darkness_min'] ?? 0),
            'darknessMax' => (float) ($row['darkness_max'] ?? 1),
            'sourceType' => ($row['source_type'] ?? 'light') === 'light'
                ? 'omni' : (string) $row['source_type'],
            'providesVision' => (bool) ($row['provides_vision'] ?? false),
            'constrainedByWalls' => (bool) ($row['constrained_by_walls'] ?? true),
            'animation' => (string) ($row['animation'] ?? 'none'),
            'animationSpeed' => (float) ($row['animation_speed'] ?? 1),
            'animationIntensity' => (float) ($row['animation_intensity'] ?? 0.5),
            'animationReverse' => (bool) ($row['animation_reverse'] ?? false),
            'brightness' => (float) ($row['brightness'] ?? 1),
            'saturation' => (float) ($row['saturation'] ?? 1),
            'contrast' => (float) ($row['contrast'] ?? 1),
            'edgeSoftness' => (float) ($row['edge_softness'] ?? 0.5),
            'transitionRatio' => (float) ($row['transition_ratio'] ?? 0.5),
            'assetUrl' => $row['asset_url'] ?? null,
            'elevation' => (float) ($row['elevation'] ?? 0),
            'enabled' => (bool) $row['enabled'],
            'hidden' => (bool) $row['hidden'],
            'revision' => (int) $row['revision'],
            'capabilities' => ['canManage' => $canManage],
        ];
    }
}
