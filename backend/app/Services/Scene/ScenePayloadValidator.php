<?php

namespace App\Services\Scene;

class ScenePayloadValidator
{
    private const MUTABLE_FIELDS = [
        'name', 'description', 'background_url', 'width', 'height', 'padding',
        'background_color', 'grid_type', 'grid_size', 'grid_distance', 'grid_unit',
        'grid_offset_x', 'grid_offset_y', 'grid_color', 'grid_opacity', 'is_visible',
        'sort_order',
    ];

    public function validateCreate(array $payload): array
    {
        return $this->validate($payload, false);
    }

    public function validateUpdate(array $payload): array
    {
        return $this->validate($payload, true);
    }

    public function validateRevision(array $payload): array
    {
        $errors = [];
        $revision = $this->positiveInteger($payload['revision'] ?? null);
        if ($revision === null) {
            $errors['revision'] = 'A positive revision is required.';
        }
        return ['valid' => !$errors, 'errors' => $errors, 'revision' => $revision];
    }

    private function validate(array $payload, bool $partial): array
    {
        $errors = [];
        $data = [];
        $allowed = array_merge(self::MUTABLE_FIELDS, $partial ? ['revision'] : []);
        foreach (array_diff(array_keys($payload), $allowed) as $field) {
            $errors[$field] = 'This field is not supported.';
        }

        if (!$partial || array_key_exists('name', $payload)) {
            $this->stringField($payload, 'name', 1, 150, false, $data, $errors);
        }
        foreach (['description' => 10000, 'background_url' => 2048] as $field => $max) {
            if (array_key_exists($field, $payload)) {
                $this->stringField($payload, $field, 0, $max, true, $data, $errors);
            }
        }
        foreach (['grid_unit' => 32] as $field => $max) {
            if (array_key_exists($field, $payload)) {
                $this->stringField($payload, $field, 1, $max, false, $data, $errors);
            }
        }
        if (isset($data['background_url']) && !$this->safeAssetUrl($data['background_url'])) {
            $errors['background_url'] = 'Only relative, http and https asset URLs are allowed.';
            unset($data['background_url']);
        }

        $integers = [
            'width' => [256, 50000], 'height' => [256, 50000], 'padding' => [0, 5000],
            'grid_size' => [1, 1000], 'sort_order' => [-100000, 100000],
        ];
        foreach ($integers as $field => $range) {
            if (array_key_exists($field, $payload)) {
                $value = filter_var($payload[$field], FILTER_VALIDATE_INT);
                if ($value === false || $value < $range[0] || $value > $range[1]) {
                    $errors[$field] = "Value must be an integer between {$range[0]} and {$range[1]}.";
                } else {
                    $data[$field] = (int) $value;
                }
            }
        }

        $numbers = [
            'grid_distance' => [0.01, 1000000], 'grid_offset_x' => [-50000, 50000],
            'grid_offset_y' => [-50000, 50000], 'grid_opacity' => [0, 1],
        ];
        foreach ($numbers as $field => $range) {
            if (array_key_exists($field, $payload)) {
                $value = filter_var($payload[$field], FILTER_VALIDATE_FLOAT);
                if ($value === false || !is_finite((float) $value) || $value < $range[0] || $value > $range[1]) {
                    $errors[$field] = "Value must be between {$range[0]} and {$range[1]}.";
                } else {
                    $data[$field] = (float) $value;
                }
            }
        }

        if (array_key_exists('grid_type', $payload)) {
            $type = strtolower(trim((string) $payload['grid_type']));
            if (!in_array($type, ['gridless', 'square', 'hex_pointy', 'hex_flat'], true)) {
                $errors['grid_type'] = 'Unsupported grid type.';
            } else {
                $data['grid_type'] = $type;
            }
        }
        foreach (['background_color', 'grid_color'] as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $color = strtoupper(trim((string) $payload[$field]));
            if (!preg_match('/^#[0-9A-F]{6}(?:[0-9A-F]{2})?$/', $color)) {
                $errors[$field] = 'Color must use #RRGGBB or #RRGGBBAA format.';
            } else {
                $data[$field] = $color;
            }
        }
        if (array_key_exists('is_visible', $payload)) {
            $visible = filter_var($payload['is_visible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($visible === null) {
                $errors['is_visible'] = 'Value must be boolean.';
            } else {
                $data['is_visible'] = $visible ? 1 : 0;
            }
        }

        $revision = null;
        if ($partial) {
            $revisionResult = $this->validateRevision($payload);
            $revision = $revisionResult['revision'];
            $errors = array_merge($errors, $revisionResult['errors']);
            if (!$data) {
                $errors['payload'] = 'At least one scene field must be changed.';
            }
        }

        return ['valid' => !$errors, 'errors' => $errors, 'data' => $data, 'revision' => $revision];
    }

    private function stringField(
        array $payload,
        string $field,
        int $min,
        int $max,
        bool $nullable,
        array &$data,
        array &$errors
    ): void {
        if ($nullable && ($payload[$field] ?? null) === null) {
            $data[$field] = null;
            return;
        }
        if (!is_string($payload[$field] ?? null)) {
            $errors[$field] = 'Value must be a string.';
            return;
        }
        $value = trim($payload[$field]);
        $length = strlen($value);
        if ($length < $min || $length > $max) {
            $errors[$field] = "Length must be between {$min} and {$max}.";
            return;
        }
        $data[$field] = $value === '' && $nullable ? null : $value;
    }

    private function safeAssetUrl(?string $url): bool
    {
        if ($url === null) {
            return true;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $url) || strpos($url, '//') === 0) {
            return false;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === false) {
            return false;
        }
        if ($scheme === null) {
            return true;
        }
        if (!in_array(strtolower($scheme), ['http', 'https'], true)) {
            return false;
        }
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function positiveInteger($value): ?int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $filtered === false ? null : (int) $filtered;
    }
}
