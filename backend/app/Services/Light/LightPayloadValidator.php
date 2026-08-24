<?php

namespace App\Services\Light;

final class LightPayloadValidator
{
    private const FIELDS = [
        'x', 'y', 'brightRadius', 'dimRadius', 'color', 'intensity', 'enabled', 'hidden',
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
        foreach (['x', 'y'] as $field) {
            if (!array_key_exists($field, $payload)) {
                if (!$partial) $errors[$field] = 'Coordinate is required.';
                continue;
            }
            $this->number($payload[$field], $field, -1000000, 1000000, $data, $errors);
        }
        foreach (['brightRadius' => 'bright_radius', 'dimRadius' => 'dim_radius'] as $field => $db) {
            if (array_key_exists($field, $payload)) {
                $this->number($payload[$field], $db, 0, 100000, $data, $errors, $field);
            }
        }
        if (array_key_exists('intensity', $payload)) {
            $this->number($payload['intensity'], 'intensity', 0, 1, $data, $errors);
        }
        if (array_key_exists('color', $payload)) {
            $color = strtoupper(trim((string) $payload['color']));
            if (!preg_match('/^#[0-9A-F]{6}(?:[0-9A-F]{2})?$/', $color)) {
                $errors['color'] = 'Color is invalid.';
            } else $data['color'] = $color;
        }
        foreach (['enabled', 'hidden'] as $field) {
            if (!array_key_exists($field, $payload)) continue;
            $value = filter_var($payload[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === null) $errors[$field] = 'A boolean is required.';
            else $data[$field] = $value ? 1 : 0;
        }
        if (!$partial) {
            $data += [
                'bright_radius' => 200, 'dim_radius' => 400, 'color' => '#FFD27A',
                'intensity' => 1, 'enabled' => 1, 'hidden' => 0,
            ];
        }
        if (isset($data['bright_radius'], $data['dim_radius'])
            && $data['bright_radius'] > $data['dim_radius']) {
            $errors['dimRadius'] = 'Dim radius must include the bright radius.';
        }
        return $partial ? $this->revision($payload, $data, $errors) : [
            'valid' => !$errors, 'data' => $data, 'errors' => $errors,
        ];
    }

    private function number(
        $value,
        string $dbField,
        float $minimum,
        float $maximum,
        array &$data,
        array &$errors,
        ?string $errorField = null
    ): void {
        $field = $errorField ?: $dbField;
        if (!is_numeric($value) || !is_finite((float) $value)
            || $value < $minimum || $value > $maximum) {
            $errors[$field] = "Value must be between {$minimum} and {$maximum}.";
        } else $data[$dbField] = (float) $value;
    }

    private function revision(
        array $payload,
        array $data,
        array $errors = [],
        bool $requiresData = true
    ): array {
        $revision = filter_var($payload['revision'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($revision === false) $errors['revision'] = 'Revision is required.';
        if ($requiresData && !$data) $errors['payload'] = 'A writable field is required.';
        return ['valid' => !$errors, 'data' => $data, 'revision' => (int) $revision, 'errors' => $errors];
    }
}
