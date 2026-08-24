<?php

namespace App\Services\Tile;

final class TilePayloadValidator
{
    private const FIELDS = [
        'name', 'assetUrl', 'mediaType', 'layer', 'x', 'y', 'width', 'height',
        'rotation', 'opacity', 'sortOrder', 'hidden', 'locked', 'autoplay', 'loop', 'muted',
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
        $this->text($payload, 'name', 150, $data, $errors, !$partial, 'Tile');
        $this->text($payload, 'assetUrl', 2048, $data, $errors, !$partial);
        if (isset($data['asset_url']) && !$this->safeUrl($data['asset_url'])) {
            $errors['assetUrl'] = 'Asset URL is unsafe.';
            unset($data['asset_url']);
        }
        foreach (['mediaType' => ['image', 'video'], 'layer' => ['background', 'foreground']] as $field => $values) {
            if (!array_key_exists($field, $payload)) continue;
            $value = strtolower(trim((string) $payload[$field]));
            if (!in_array($value, $values, true)) $errors[$field] = 'Value is invalid.';
            else $data[$this->snake($field)] = $value;
        }
        foreach (['x', 'y', 'rotation'] as $field) {
            $this->number($payload, $field, -1000000, 1000000, $data, $errors);
        }
        foreach (['width', 'height'] as $field) {
            $this->number($payload, $field, 8, 50000, $data, $errors);
        }
        $this->number($payload, 'opacity', 0, 1, $data, $errors);
        if (array_key_exists('sortOrder', $payload)) {
            $value = filter_var($payload['sortOrder'], FILTER_VALIDATE_INT);
            if ($value === false || $value < -100000 || $value > 100000) {
                $errors['sortOrder'] = 'Sort order is invalid.';
            } else $data['sort_order'] = (int) $value;
        }
        foreach (['hidden', 'locked', 'autoplay', 'loop', 'muted'] as $field) {
            if (!array_key_exists($field, $payload)) continue;
            $value = filter_var($payload[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === null) $errors[$field] = 'A boolean is required.';
            else $data[$field] = $value ? 1 : 0;
        }
        if (!$partial) {
            $data += [
                'media_type' => 'image', 'layer' => 'background', 'x' => 0, 'y' => 0,
                'width' => 200, 'height' => 200, 'rotation' => 0, 'opacity' => 1,
                'sort_order' => 0, 'hidden' => 0, 'locked' => 0,
                'autoplay' => 1, 'loop' => 1, 'muted' => 1,
            ];
        }
        return $partial ? $this->revision($payload, $data, $errors) : [
            'valid' => !$errors, 'data' => $data, 'errors' => $errors,
        ];
    }

    private function text(
        array $payload,
        string $field,
        int $limit,
        array &$data,
        array &$errors,
        bool $required,
        ?string $fallback = null
    ): void {
        if (!array_key_exists($field, $payload)) {
            if ($required && $fallback === null) $errors[$field] = 'Value is required.';
            elseif ($fallback !== null) $data[$this->snake($field)] = $fallback;
            return;
        }
        $value = trim((string) $payload[$field]);
        if (($required && $value === '') || mb_strlen($value) > $limit) {
            $errors[$field] = 'Value is invalid.';
        } else $data[$this->snake($field)] = $value;
    }

    private function number(
        array $payload,
        string $field,
        float $minimum,
        float $maximum,
        array &$data,
        array &$errors
    ): void {
        if (!array_key_exists($field, $payload)) return;
        $value = $payload[$field];
        if (!is_numeric($value) || !is_finite((float) $value) || $value < $minimum || $value > $maximum) {
            $errors[$field] = "Value must be between {$minimum} and {$maximum}.";
        } else $data[$this->snake($field)] = (float) $value;
    }

    private function revision(array $payload, array $data, array $errors = [], bool $requiresData = true): array
    {
        $revision = filter_var($payload['revision'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($revision === false) $errors['revision'] = 'Revision is required.';
        if ($requiresData && !$data) $errors['payload'] = 'A writable field is required.';
        return ['valid' => !$errors, 'data' => $data, 'revision' => (int) $revision, 'errors' => $errors];
    }

    private function safeUrl(string $value): bool
    {
        if ($value === '' || strpos($value, '//') === 0) return false;
        if (strpos($value, '/') === 0) return true;
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true)
            && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function snake(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}
