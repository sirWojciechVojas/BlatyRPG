<?php

namespace App\Services\Wall;

final class WallPayloadValidator
{
    private const TYPES = ['wall', 'door', 'window', 'secret', 'terrain', 'invisible', 'ethereal'];
    private const WALL_TYPES = ['solid', 'terrain', 'invisible', 'ethereal', 'custom'];
    private const DOOR_TYPES = ['none', 'door', 'secret', 'window'];
    private const RESTRICTIONS = ['normal', 'limited', 'proximity'];
    private const STATES = ['closed', 'open', 'locked'];
    private const FIELDS = [
        'name', 'type', 'x1', 'y1', 'x2', 'y2', 'blocksMovement', 'blocksSight',
        'blocksLight', 'doorState', 'color', 'enabled', 'hidden',
        'wallType', 'doorType', 'restrictionType', 'blocksSound',
        'proximityThreshold', 'playerOperable', 'soundConfig', 'animationConfig',
    ];

    public function create(array $payload): array
    {
        return $this->validate($payload, false);
    }

    public function update(array $payload): array
    {
        return $this->validate($payload, true);
    }

    public function deletion(array $payload): array
    {
        return $this->revision($payload, [], [], false);
    }

    private function validate(array $payload, bool $partial): array
    {
        $data = [];
        $errors = [];
        $allowed = $partial ? array_merge(self::FIELDS, ['revision']) : self::FIELDS;
        foreach (array_diff(array_keys($payload), $allowed) as $field) {
            $errors[$field] = 'Field is not writable.';
        }
        foreach (['x1', 'y1', 'x2', 'y2'] as $field) {
            if (!array_key_exists($field, $payload)) {
                if (!$partial) $errors[$field] = 'Coordinate is required.';
                continue;
            }
            if (!is_numeric($payload[$field]) || abs((float) $payload[$field]) > 1000000) {
                $errors[$field] = 'Coordinate is invalid.';
            } else $data[$field] = (float) $payload[$field];
        }
        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if ($name === '' || mb_strlen($name) > 150) $errors['name'] = 'Name is invalid.';
            else $data['name'] = $name;
        }
        $type = strtolower(trim((string) ($payload['type'] ?? 'wall')));
        if (array_key_exists('type', $payload) || !$partial) {
            if (!in_array($type, self::TYPES, true)) $errors['type'] = 'Wall type is invalid.';
            else $data['type'] = $type;
        }
        foreach (['wallType' => ['wall_type', self::WALL_TYPES],
            'doorType' => ['door_type', self::DOOR_TYPES],
            'restrictionType' => ['restriction_type', self::RESTRICTIONS]]
            as $field => [$column, $values]) {
            if (!array_key_exists($field, $payload)) continue;
            $value = strtolower(trim((string) $payload[$field]));
            if (!in_array($value, $values, true)) $errors[$field] = 'Value is invalid.';
            else $data[$column] = $value;
        }
        foreach (['blocksMovement', 'blocksSight', 'blocksLight', 'blocksSound',
            'playerOperable', 'enabled', 'hidden'] as $field) {
            if (!array_key_exists($field, $payload)) continue;
            $value = filter_var($payload[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === null) $errors[$field] = 'A boolean is required.';
            else $data[$this->snake($field)] = $value ? 1 : 0;
        }
        if (array_key_exists('proximityThreshold', $payload)) {
            $threshold = $payload['proximityThreshold'];
            if (!is_numeric($threshold) || !is_finite((float) $threshold)
                || (float) $threshold < 0 || (float) $threshold > 100000) {
                $errors['proximityThreshold'] = 'Proximity threshold is invalid.';
            } else $data['proximity_threshold'] = (float) $threshold;
        }
        if (array_key_exists('soundConfig', $payload)) {
            if (!is_array($payload['soundConfig'])) {
                $errors['soundConfig'] = 'Configuration must be an object.';
            } elseif (strlen((string) json_encode($payload['soundConfig'])) > 16384) {
                $errors['soundConfig'] = 'Configuration is too large.';
            } else {
                $sound = (new WallSoundConfigValidator())->validate($payload['soundConfig']);
                if (!$sound['valid']) $errors += $sound['errors'];
                else $data['sound_config_json'] = json_encode($sound['config']);
            }
        }
        if (array_key_exists('animationConfig', $payload)) {
            if (!is_array($payload['animationConfig'])) {
                $errors['animationConfig'] = 'Configuration must be an object.';
            } elseif (strlen((string) json_encode($payload['animationConfig'])) > 16384) {
                $errors['animationConfig'] = 'Configuration is too large.';
            } else $data['animation_config_json'] = json_encode($payload['animationConfig']);
        }
        if (array_key_exists('color', $payload)) {
            if ($payload['color'] === null || trim((string) $payload['color']) === '') {
                $data['color'] = null;
            } else {
                $color = strtoupper(trim((string) $payload['color']));
                if (!preg_match('/^#[0-9A-F]{6}([0-9A-F]{2})?$/', $color)) {
                    $errors['color'] = 'Color must be a hexadecimal CSS color.';
                } else $data['color'] = $color;
            }
        }
        if (array_key_exists('doorState', $payload)) {
            $state = strtolower(trim((string) $payload['doorState']));
            if (!in_array($state, self::STATES, true)) $errors['doorState'] = 'Door state is invalid.';
            else $data['door_state'] = $state;
        } elseif (!$partial && in_array($type, ['door', 'secret', 'window'], true)) $data['door_state'] = 'closed';
        if (!$partial || array_key_exists('type', $payload)) $this->applyLegacyPreset($type, $data);
        if (!$partial && in_array(($data['door_type'] ?? 'none'), ['door', 'secret', 'window'], true)) {
            $data += ['door_state' => 'closed'];
        }
        if (!$partial) $data += [
            'blocks_movement' => 1, 'blocks_sight' => 1, 'blocks_light' => 1,
            'blocks_sound' => 1, 'wall_type' => 'solid', 'door_type' => 'none',
            'restriction_type' => 'normal', 'proximity_threshold' => 10,
            'player_operable' => 1, 'enabled' => 1, 'hidden' => 0,
        ];
        if ($this->zeroLength($data, $partial)) $errors['geometry'] = 'Wall must have a length.';
        return $partial ? $this->revision($payload, $data, $errors) : [
            'valid' => !$errors, 'data' => $data, 'errors' => $errors,
        ];
    }

    private function revision(
        array $payload,
        array $data,
        array $errors = [],
        bool $requiresData = true
    ): array
    {
        $revision = filter_var($payload['revision'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($revision === false) $errors['revision'] = 'Revision is required.';
        if ($requiresData && $data === []) $errors['payload'] = 'A writable field is required.';
        return ['valid' => !$errors, 'data' => $data, 'revision' => (int) $revision, 'errors' => $errors];
    }

    private function zeroLength(array $data, bool $partial): bool
    {
        if ($partial && count(array_intersect(['x1', 'y1', 'x2', 'y2'], array_keys($data))) < 4) return false;
        return isset($data['x1'], $data['y1'], $data['x2'], $data['y2'])
            && $data['x1'] === $data['x2'] && $data['y1'] === $data['y2'];
    }

    private function snake(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    private function applyLegacyPreset(string $type, array &$data): void
    {
        if ($type === 'wall') {
            $data += ['wall_type' => 'solid', 'door_type' => 'none'];
        } elseif ($type === 'terrain') {
            $data += ['wall_type' => 'terrain', 'door_type' => 'none',
                'restriction_type' => 'limited'];
        } elseif ($type === 'invisible') {
            $data += ['wall_type' => 'invisible', 'door_type' => 'none', 'blocks_sight' => 0,
                'blocks_light' => 0, 'blocks_sound' => 0];
        } elseif ($type === 'ethereal') {
            $data += ['wall_type' => 'ethereal', 'door_type' => 'none', 'blocks_movement' => 0,
                'blocks_sound' => 0];
        } elseif (in_array($type, ['door', 'secret', 'window'], true)) {
            $data += ['door_type' => $type];
            if ($type === 'window') {
                $data += ['blocks_sight' => 0, 'blocks_light' => 0,
                    'restriction_type' => 'proximity'];
            }
        }
    }
}
