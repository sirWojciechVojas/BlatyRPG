<?php

namespace App\Services\Token;

final class TokenPayloadValidator
{
    private const TEXT_LIMITS = ['name' => 150, 'imageUrl' => 2048];
    private const NUMBERS = [
        'x', 'y', 'width', 'height', 'rotation', 'facing', 'elevation',
        'movementRange', 'movementSpent',
    ];
    private const DISPOSITIONS = ['friendly', 'neutral', 'hostile', 'secret'];
    private const WRITABLE = [
        'characterId', 'name', 'imageUrl', 'x', 'y', 'width', 'height',
        'rotation', 'facing', 'elevation', 'disposition', 'hidden', 'locked',
        'rotationHandleEnabled', 'facingHandleEnabled',
        'movementRange', 'movementSpent', 'movementResetMode',
        'visibleTo', 'controlledBy', 'editableBy', 'observerBy',
        'statuses', 'resources',
    ];
    private const PERMISSIONS = [
        'visibleTo' => 'visible_to_json',
        'controlledBy' => 'controlled_by_json',
        'editableBy' => 'editable_by_json',
        'observerBy' => 'observer_by_json',
    ];

    public function create(array $payload): array
    {
        $result = $this->fields($payload, false);
        $this->unknownFields($payload, self::WRITABLE, $result);
        if (!isset($result['data']['name']) || $result['data']['name'] === '') {
            $result['errors']['name'] = 'Name is required.';
        }
        return $this->result($result);
    }

    public function update(array $payload): array
    {
        $result = $this->fields($payload, true);
        $this->unknownFields($payload, array_merge(self::WRITABLE, ['revision']), $result);
        $revision = filter_var($payload['revision'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($revision === false) $result['errors']['revision'] = 'Revision is required.';
        else $result['revision'] = (int) $revision;
        if (!$result['data']) $result['errors']['payload'] = 'At least one token field is required.';
        return $this->result($result);
    }

    public function deletion(array $payload): array
    {
        $revision = filter_var($payload['revision'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        return $revision === false
            ? ['valid' => false, 'errors' => ['revision' => 'Revision is required.']]
            : ['valid' => true, 'revision' => (int) $revision, 'errors' => []];
    }

    private function fields(array $payload, bool $partial): array
    {
        $result = ['data' => [], 'errors' => []];
        foreach (self::TEXT_LIMITS as $field => $limit) {
            if (!array_key_exists($field, $payload)) continue;
            $value = trim((string) $payload[$field]);
            if ($field === 'name' && $value === '') $result['errors'][$field] = 'Name is required.';
            elseif (mb_strlen($value) > $limit) $result['errors'][$field] = 'Value is too long.';
            elseif ($field === 'imageUrl' && !$this->safeUrl($value)) $result['errors'][$field] = 'Image URL is unsafe.';
            else $result['data'][$this->snake($field)] = $value ?: null;
        }
        foreach (self::NUMBERS as $field) {
            if (!array_key_exists($field, $payload)) continue;
            if (!is_numeric($payload[$field]) || !is_finite((float) $payload[$field])) {
                $result['errors'][$field] = 'A finite number is required.';
            } else {
                $value = (float) $payload[$field];
                $result['data'][$this->snake($field)] = in_array($field, ['rotation', 'facing'], true)
                    ? $this->angle($value)
                    : $value;
            }
        }
        foreach (['width', 'height'] as $field) {
            if (isset($result['data'][$field]) && ($result['data'][$field] < 1 || $result['data'][$field] > 10000)) {
                $result['errors'][$field] = 'Size must be between 1 and 10000.';
            }
        }
        foreach (['movementRange', 'movementSpent'] as $field) {
            $databaseField = $this->snake($field);
            if (isset($result['data'][$databaseField])
                && ($result['data'][$databaseField] < 0 || $result['data'][$databaseField] > 10000)) {
                $result['errors'][$field] = 'Movement must be between 0 and 10000.';
            }
        }
        $this->optionalId($payload, 'characterId', $result);
        foreach (['hidden', 'locked', 'rotationHandleEnabled', 'facingHandleEnabled'] as $field) {
            if (!array_key_exists($field, $payload)) continue;
            $value = filter_var($payload[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === null) $result['errors'][$field] = 'A boolean is required.';
            else $result['data'][$this->snake($field)] = $value ? 1 : 0;
        }
        if (array_key_exists('disposition', $payload)) {
            $value = strtolower(trim((string) $payload['disposition']));
            if (!in_array($value, self::DISPOSITIONS, true)) $result['errors']['disposition'] = 'Disposition is invalid.';
            else $result['data']['disposition'] = $value;
        }
        if (array_key_exists('movementResetMode', $payload)) {
            $value = strtolower(trim((string) $payload['movementResetMode']));
            if (!in_array($value, ['turn', 'round', 'manual'], true)) {
                $result['errors']['movementResetMode'] = 'Movement reset mode is invalid.';
            } else $result['data']['movement_reset_mode'] = $value;
        }
        foreach (self::PERMISSIONS as $field => $databaseField) {
            if (!array_key_exists($field, $payload)) continue;
            $scope = TokenPermissionScope::validate($payload[$field]);
            if (!$scope['valid']) $result['errors'][$field] = $scope['error'];
            else $result['data'][$databaseField] = $scope['data'];
        }
        if (array_key_exists('statuses', $payload)) {
            $statuses = TokenStatusValidator::validate($payload['statuses']);
            if (!$statuses['valid']) $result['errors']['statuses'] = $statuses['error'];
            else $result['data']['statuses_json'] = $statuses['data'];
        }
        if (array_key_exists('resources', $payload)) {
            $resources = TokenResourceValidator::validate($payload['resources']);
            if (!$resources['valid']) $result['errors']['resources'] = $resources['error'];
            else $result['data']['bars_json'] = $resources['data'];
        }
        if (!$partial) {
            $result['data'] += ['x' => 0, 'y' => 0];
            $result['data']['facing'] ??= $result['data']['rotation'] ?? 0;
            $hidden = !empty($result['data']['hidden']);
            $result['data'] += [
                'visible_to_json' => $this->scope($hidden ? 'gm' : 'everyone'),
                'controlled_by_json' => $this->scope('inherit'),
                'editable_by_json' => $this->scope('gm'),
                'observer_by_json' => $this->scope('inherit'),
            ];
        }
        return $result;
    }

    private function optionalId(array $payload, string $field, array &$result): void
    {
        if (!array_key_exists($field, $payload)) return;
        if ($payload[$field] === null || $payload[$field] === '') {
            $result['data'][$this->snake($field)] = null;
            return;
        }
        $id = filter_var($payload[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) $result['errors'][$field] = 'A valid id is required.';
        else $result['data'][$this->snake($field)] = (int) $id;
    }

    private function result(array $result): array
    {
        return ['valid' => !$result['errors']] + $result;
    }

    private function unknownFields(array $payload, array $allowed, array &$result): void
    {
        foreach (array_diff(array_keys($payload), $allowed) as $field) {
            $result['errors'][$field] = 'Field is not writable.';
        }
    }

    private function safeUrl(string $value): bool
    {
        if ($value === '' || strpos($value, '/') === 0) return true;
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true)
            && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function snake(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    private function angle(float $value): float
    {
        $angle = fmod($value, 360.0);
        return round($angle < 0 ? $angle + 360.0 : $angle, 3);
    }

    private function scope(string $mode): array
    {
        return ['mode' => $mode, 'userIds' => []];
    }
}
