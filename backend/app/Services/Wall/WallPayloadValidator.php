<?php

namespace App\Services\Wall;

final class WallPayloadValidator
{
    private const TYPES = ['wall', 'door', 'secret'];
    private const STATES = ['closed', 'open', 'locked'];
    private const FIELDS = [
        'type', 'x1', 'y1', 'x2', 'y2', 'blocksMovement', 'blocksSight',
        'blocksLight', 'doorState',
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
        $type = strtolower(trim((string) ($payload['type'] ?? 'wall')));
        if (array_key_exists('type', $payload) || !$partial) {
            if (!in_array($type, self::TYPES, true)) $errors['type'] = 'Wall type is invalid.';
            else $data['type'] = $type;
        }
        foreach (['blocksMovement', 'blocksSight', 'blocksLight'] as $field) {
            if (!array_key_exists($field, $payload)) continue;
            $value = filter_var($payload[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === null) $errors[$field] = 'A boolean is required.';
            else $data[$this->snake($field)] = $value ? 1 : 0;
        }
        if (array_key_exists('doorState', $payload)) {
            $state = strtolower(trim((string) $payload['doorState']));
            if (!in_array($state, self::STATES, true)) $errors['doorState'] = 'Door state is invalid.';
            else $data['door_state'] = $state;
        } elseif (!$partial && in_array($type, ['door', 'secret'], true)) $data['door_state'] = 'closed';
        if (!$partial) $data += ['blocks_movement' => 1, 'blocks_sight' => 1, 'blocks_light' => 1];
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
}
