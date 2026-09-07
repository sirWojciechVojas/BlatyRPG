<?php

namespace App\Services\Wall;

final class WallPresenter
{
    public static function present(array $row, bool $canManage): array
    {
        $secret = ($row['type'] ?? 'wall') === 'secret';
        return [
            'id' => (int) $row['id'],
            'sceneId' => (int) $row['scene_id'],
            'name' => $secret && !$canManage
                ? ('Wall ' . $row['id'])
                : (string) (($row['name'] ?? '') ?: ('Wall ' . $row['id'])),
            'type' => $secret && !$canManage ? 'wall' : (string) $row['type'],
            'x1' => (float) $row['x1'],
            'y1' => (float) $row['y1'],
            'x2' => (float) $row['x2'],
            'y2' => (float) $row['y2'],
            'blocksMovement' => (bool) $row['blocks_movement'],
            'blocksSight' => (bool) $row['blocks_sight'],
            'blocksLight' => (bool) $row['blocks_light'],
            'doorState' => $secret && !$canManage ? null : ($row['door_state'] ?? null),
            'color' => $canManage ? ($row['color'] ?? null) : null,
            'enabled' => !array_key_exists('enabled', $row) || (bool) $row['enabled'],
            'hidden' => $canManage && (bool) ($row['hidden'] ?? false),
            'revision' => (int) $row['revision'],
            'capabilities' => ['canManage' => $canManage],
        ];
    }
}
