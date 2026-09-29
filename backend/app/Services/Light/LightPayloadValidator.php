<?php

namespace App\Services\Light;

final class LightPayloadValidator
{
    private const FIELDS = [
        'x', 'y', 'brightRadius', 'dimRadius', 'color', 'intensity', 'opacity',
        'softness', 'clarity', 'gradualIllumination', 'darknessMin',
        'darknessMax',
        'sourceType', 'providesVision', 'constrainedByWalls', 'animation',
        'animationSpeed', 'animationIntensity', 'elevation', 'enabled', 'hidden',
        'name', 'lumens', 'direction', 'angle', 'areaWidth', 'areaHeight',
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
        foreach (['lumens' => ['lumens', 0, 1000000],
            'direction' => ['direction', 0, 360], 'angle' => ['angle', 1, 360],
            'areaWidth' => ['area_width', 1, 100000],
            'areaHeight' => ['area_height', 1, 100000]]
            as $field => [$db, $minimum, $maximum]) {
            if (array_key_exists($field, $payload)) {
                $this->number(
                    $payload[$field], $db, $minimum, $maximum, $data, $errors, $field
                );
                if ($field === 'lumens' && isset($data[$db])) {
                    $data[$db] = (int) round($data[$db]);
                    $data['intensity'] = min(1, $data[$db] / 800);
                }
            }
        }
        foreach (['intensity' => 'intensity', 'opacity' => 'opacity',
            'softness' => 'softness', 'clarity' => 'clarity',
            'darknessMin' => 'darkness_min',
            'darknessMax' => 'darkness_max', 'animationIntensity' => 'animation_intensity']
            as $field => $db) {
            if (array_key_exists($field, $payload)) {
                $this->number($payload[$field], $db, 0, 1, $data, $errors, $field);
            }
        }
        if (array_key_exists('animationSpeed', $payload)) {
            $this->number($payload['animationSpeed'], 'animation_speed', 0.1, 10, $data, $errors, 'animationSpeed');
        }
        if (array_key_exists('elevation', $payload)) {
            $this->number($payload['elevation'], 'elevation', -1000000, 1000000, $data, $errors);
        }
        if (array_key_exists('color', $payload)) {
            $color = strtoupper(trim((string) $payload['color']));
            if (!preg_match('/^#[0-9A-F]{6}(?:[0-9A-F]{2})?$/', $color)) {
                $errors['color'] = 'Color is invalid.';
            } else $data['color'] = $color;
        }
        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if ($name === '' || strlen($name) > 100) {
                $errors['name'] = 'Name must contain between 1 and 100 characters.';
            } else $data['name'] = $name;
        }
        foreach (['enabled' => 'enabled', 'hidden' => 'hidden',
            'gradualIllumination' => 'gradual_illumination',
            'providesVision' => 'provides_vision',
            'constrainedByWalls' => 'constrained_by_walls'] as $field => $db) {
            if (!array_key_exists($field, $payload)) continue;
            $value = filter_var($payload[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === null) $errors[$field] = 'A boolean is required.';
            else $data[$db] = $value ? 1 : 0;
        }
        foreach (['sourceType' => ['source_type', [
            'light', 'omni', 'directional', 'cone', 'area', 'darkness',
        ]],
            'animation' => ['animation', ['none', 'flicker', 'pulse', 'vortex']]]
            as $field => [$db, $allowedValues]) {
            if (!array_key_exists($field, $payload)) continue;
            $value = strtolower(trim((string) $payload[$field]));
            if (!in_array($value, $allowedValues, true)) $errors[$field] = 'Value is invalid.';
            else $data[$db] = $field === 'sourceType' && $value === 'light'
                ? 'omni' : $value;
        }
        if (!$partial) {
            $data += [
                'bright_radius' => 200, 'dim_radius' => 400, 'color' => '#FFD27A',
                'intensity' => 1, 'opacity' => 1, 'softness' => 0.5,
                'clarity' => 0,
                'gradual_illumination' => 1, 'darkness_min' => 0,
                'darkness_max' => 1, 'source_type' => 'omni',
                'provides_vision' => 0, 'constrained_by_walls' => 1,
                'animation' => 'none', 'animation_speed' => 1,
                'animation_intensity' => 0.5, 'elevation' => 0,
                'enabled' => 1, 'hidden' => 0,
                'name' => 'Light', 'lumens' => 800, 'direction' => 0,
                'angle' => 90, 'area_width' => 400, 'area_height' => 400,
            ];
        }
        if (isset($data['bright_radius'], $data['dim_radius'])
            && $data['bright_radius'] > $data['dim_radius']) {
            $errors['dimRadius'] = 'Dim radius must include the bright radius.';
        }
        if (isset($data['darkness_min'], $data['darkness_max'])
            && $data['darkness_min'] > $data['darkness_max']) {
            $errors['darknessMax'] = 'Maximum darkness must include the minimum.';
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
