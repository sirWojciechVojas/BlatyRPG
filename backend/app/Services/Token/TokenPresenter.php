<?php

namespace App\Services\Token;

final class TokenPresenter
{
    public static function present(
        array $row,
        bool $canControl,
        bool $canManage,
        bool $canEdit = false,
        bool $canObserve = false
    ): array {
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
            'visibleTo' => TokenPermissionScope::stored(
                $row['visible_to_json'] ?? null,
                !empty($row['hidden']) ? 'gm' : 'everyone'
            ),
            'controlledBy' => TokenPermissionScope::stored(
                $row['controlled_by_json'] ?? null,
                'inherit'
            ),
            'editableBy' => TokenPermissionScope::stored(
                $row['editable_by_json'] ?? null,
                'gm'
            ),
            'observerBy' => TokenPermissionScope::stored(
                $row['observer_by_json'] ?? null,
                'inherit'
            ),
            'bars' => (array) ($row['bars_json'] ?? []),
            'statuses' => (array) ($row['statuses_json'] ?? []),
            'vision' => (array) ($row['vision_json'] ?? []),
            'light' => (array) ($row['light_json'] ?? []),
            'revision' => (int) $row['revision'],
            'capabilities' => [
                'canControl' => $canControl,
                'canEdit' => $canEdit,
                'canObserve' => $canObserve,
                'canManage' => $canManage,
            ],
        ];
    }
}
