<?php

namespace App\Services\Token;

final class TokenPresenter
{
    public static function present(array $row, bool $canControl, bool $canManage): array
    {
        return [
            'id' => (int) $row['id'],
            'sceneId' => (int) $row['scene_id'],
            'characterId' => isset($row['character_id']) ? (int) $row['character_id'] : null,
            'name' => (string) $row['name'],
            'imageUrl' => (string) ($row['image_url'] ?? ''),
            'x' => (float) $row['x'],
            'y' => (float) $row['y'],
            'width' => (float) $row['width'],
            'height' => (float) $row['height'],
            'rotation' => (float) $row['rotation'],
            'facing' => (float) ($row['facing'] ?? $row['rotation']),
            'elevation' => (float) $row['elevation'],
            'disposition' => (string) $row['disposition'],
            'hidden' => (bool) $row['hidden'],
            'locked' => (bool) $row['locked'],
            'bars' => (array) ($row['bars_json'] ?? []),
            'statuses' => (array) ($row['statuses_json'] ?? []),
            'vision' => (array) ($row['vision_json'] ?? []),
            'light' => (array) ($row['light_json'] ?? []),
            'revision' => (int) $row['revision'],
            'capabilities' => [
                'canControl' => $canControl && empty($row['locked']),
                'canManage' => $canManage,
            ],
        ];
    }
}
