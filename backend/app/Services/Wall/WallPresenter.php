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
            'type' => $secret && !$canManage ? 'wall' : (string) $row['type'],
            'x1' => (float) $row['x1'],
            'y1' => (float) $row['y1'],
            'x2' => (float) $row['x2'],
            'y2' => (float) $row['y2'],
            'blocksMovement' => (bool) $row['blocks_movement'],
            'blocksSight' => (bool) $row['blocks_sight'],
            'blocksLight' => (bool) $row['blocks_light'],
            'doorState' => $secret && !$canManage ? null : ($row['door_state'] ?? null),
            'revision' => (int) $row['revision'],
            'capabilities' => ['canManage' => $canManage],
        ];
    }
}
