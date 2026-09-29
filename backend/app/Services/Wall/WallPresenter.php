<?php

namespace App\Services\Wall;

final class WallPresenter
{
    public static function present(array $row, bool $canManage): array
    {
        $doorType = (string) ($row['door_type'] ?? (
            in_array(($row['type'] ?? 'wall'), ['door', 'secret', 'window'], true)
                ? $row['type'] : 'none'
        ));
        $secret = $doorType === 'secret' || ($row['type'] ?? 'wall') === 'secret';
        return [
            'id' => (int) $row['id'],
            'sceneId' => (int) $row['scene_id'],
            'name' => $secret && !$canManage
                ? ('Wall ' . $row['id'])
                : (string) (($row['name'] ?? '') ?: ('Wall ' . $row['id'])),
            'type' => $secret && !$canManage ? 'wall' : (string) $row['type'],
            'wallType' => (string) ($row['wall_type'] ?? 'solid'),
            'doorType' => $secret && !$canManage ? 'none' : $doorType,
            'restrictionType' => (string) ($row['restriction_type'] ?? 'normal'),
            'x1' => (float) $row['x1'],
            'y1' => (float) $row['y1'],
            'x2' => (float) $row['x2'],
            'y2' => (float) $row['y2'],
            'blocksMovement' => (bool) $row['blocks_movement'],
            'blocksSight' => (bool) $row['blocks_sight'],
            'blocksLight' => (bool) $row['blocks_light'],
            'blocksSound' => (bool) ($row['blocks_sound'] ?? true),
            'doorState' => $secret && !$canManage ? null : ($row['door_state'] ?? null),
            'color' => $canManage ? ($row['color'] ?? null) : null,
            'enabled' => !array_key_exists('enabled', $row) || (bool) $row['enabled'],
            'hidden' => $canManage && (bool) ($row['hidden'] ?? false),
            'proximityThreshold' => (float) ($row['proximity_threshold'] ?? 10),
            'playerOperable' => (bool) ($row['player_operable'] ?? true),
            'soundConfig' => ($canManage || !$secret)
                ? self::json($row['sound_config_json'] ?? null) : [],
            'animationConfig' => ($canManage || !$secret)
                ? self::json($row['animation_config_json'] ?? null) : [],
            'revision' => (int) $row['revision'],
            'capabilities' => ['canManage' => $canManage],
        ];
    }

    private static function json($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value)) return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
