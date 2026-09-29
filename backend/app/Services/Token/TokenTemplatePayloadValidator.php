<?php

namespace App\Services\Token;

final class TokenTemplatePayloadValidator
{
    private const WRITABLE = [
        'name', 'imageUrl', 'widthCells', 'heightCells', 'rotation', 'facing',
        'rotationHandleEnabled', 'facingHandleEnabled', 'rotationFollowsFacing',
        'showInfoUnselected', 'resourceBarPosition', 'elevation', 'disposition',
        'movementRange', 'movementResetMode', 'resources', 'vision',
    ];
    private const DISPOSITIONS = ['friendly', 'neutral', 'hostile', 'secret'];
    private const BAR_POSITIONS = ['above', 'top-overlap', 'bottom-overlap', 'below'];

    public function create(array $payload, bool $hasUpload = false): array
    {
        $result = $this->fields($payload, false);
        $this->unknownFields($payload, self::WRITABLE, $result);
        if (($result['data']['name'] ?? '') === '') {
            $result['errors']['name'] = 'Name is required.';
        }
        if (!$hasUpload && empty($result['data']['image_url'])) {
            $result['errors']['image'] = 'An image URL or uploaded image is required.';
        }
        if ($hasUpload && !empty($result['data']['image_url'])) {
            $result['errors']['image'] = 'Use either an image URL or an uploaded image.';
        }
        return $this->result($result);
    }

    public function update(array $payload, bool $hasUpload = false): array
    {
        $result = $this->fields($payload, true);
        $this->unknownFields($payload, array_merge(self::WRITABLE, ['revision']), $result);
        $revision = filter_var($payload['revision'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($revision === false) $result['errors']['revision'] = 'Revision is required.';
        else $result['revision'] = (int) $revision;
        if ($hasUpload && !empty($result['data']['image_url'])) {
            $result['errors']['image'] = 'Use either an image URL or an uploaded image.';
        }
        if (!$result['data'] && !$hasUpload) {
            $result['errors']['payload'] = 'At least one template field is required.';
        }
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
        foreach (['name' => 150, 'imageUrl' => 2048] as $field => $limit) {
            if (!array_key_exists($field, $payload)) continue;
            $value = trim((string) $payload[$field]);
            if ($field === 'name' && $value === '') $result['errors'][$field] = 'Name is required.';
            elseif (mb_strlen($value) > $limit) $result['errors'][$field] = 'Value is too long.';
            elseif ($field === 'imageUrl' && !$this->safeUrl($value)) $result['errors'][$field] = 'Image URL is unsafe.';
            else $result['data'][$this->snake($field)] = $value ?: null;
        }
        foreach (['widthCells', 'heightCells', 'rotation', 'facing', 'elevation', 'movementRange'] as $field) {
            if (!array_key_exists($field, $payload)) continue;
            if (!is_numeric($payload[$field]) || !is_finite((float) $payload[$field])) {
                $result['errors'][$field] = 'A finite number is required.';
                continue;
            }
            $value = (float) $payload[$field];
            if (in_array($field, ['widthCells', 'heightCells'], true) && ($value < 0.25 || $value > 100)) {
                $result['errors'][$field] = 'Size must be between 0.25 and 100 grid cells.';
                continue;
            }
            if ($field === 'movementRange' && ($value < 0 || $value > 10000)) {
                $result['errors'][$field] = 'Movement must be between 0 and 10000.';
                continue;
            }
            $result['data'][$this->snake($field)] = in_array($field, ['rotation', 'facing'], true)
                ? $this->angle($value) : round($value, 3);
        }
        foreach (['rotationHandleEnabled', 'facingHandleEnabled', 'rotationFollowsFacing', 'showInfoUnselected'] as $field) {
            if (!array_key_exists($field, $payload)) continue;
            $value = filter_var($payload[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === null) $result['errors'][$field] = 'A boolean is required.';
            else $result['data'][$this->snake($field)] = $value ? 1 : 0;
        }
        $this->enum($payload, 'disposition', self::DISPOSITIONS, 'disposition', $result);
        $this->enum($payload, 'resourceBarPosition', self::BAR_POSITIONS, 'resource_bar_position', $result);
        $this->enum($payload, 'movementResetMode', ['turn', 'round', 'manual'], 'movement_reset_mode', $result);
        if (array_key_exists('resources', $payload)) {
            $resources = TokenResourceValidator::validate($payload['resources']);
            if (!$resources['valid']) $result['errors']['resources'] = $resources['error'];
            else $result['data']['bars_json'] = $resources['data'];
        }
        if (array_key_exists('vision', $payload)) {
            $vision = TokenVisionValidator::validate($payload['vision']);
            if (!$vision['valid']) $result['errors']['vision'] = $vision['error'];
            else $result['data']['vision_json'] = $vision['data'];
        }
        if (!$partial) {
            $result['data'] += [
                'width_cells' => 1.0, 'height_cells' => 1.0,
                'rotation' => 0.0, 'facing' => 0.0,
                'rotation_handle_enabled' => 1, 'facing_handle_enabled' => 1,
                'rotation_follows_facing' => 0, 'show_info_unselected' => 0,
                'resource_bar_position' => 'below', 'elevation' => 0.0,
                'disposition' => 'neutral', 'movement_range' => 6.0,
                'movement_reset_mode' => 'turn',
                'bars_json' => TokenResourceValidator::stored([]),
                'vision_json' => TokenVisionValidator::validate([])['data'],
            ];
            if (!array_key_exists('facing', $payload)) {
                $result['data']['facing'] = $result['data']['rotation'];
            }
        }
        return $result;
    }

    private function enum(array $payload, string $field, array $allowed, string $databaseField, array &$result): void
    {
        if (!array_key_exists($field, $payload)) return;
        $value = strtolower(trim((string) $payload[$field]));
        if (!in_array($value, $allowed, true)) $result['errors'][$field] = 'Value is invalid.';
        else $result['data'][$databaseField] = $value;
    }

    private function unknownFields(array $payload, array $allowed, array &$result): void
    {
        foreach (array_diff(array_keys($payload), $allowed) as $field) {
            $result['errors'][$field] = 'Field is not writable.';
        }
    }

    private function safeUrl(string $value): bool
    {
        if ($value === '') return true;
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return $scheme === 'https'
            && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function result(array $result): array
    {
        return ['valid' => !$result['errors']] + $result;
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
}
