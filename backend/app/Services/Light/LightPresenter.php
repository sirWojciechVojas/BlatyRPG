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
            'enabled' => (bool) $row['enabled'],
            'hidden' => (bool) $row['hidden'],
            'revision' => (int) $row['revision'],
            'capabilities' => ['canManage' => $canManage],
        ];
    }
}
