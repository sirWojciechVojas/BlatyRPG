<?php

namespace App\Services\Region;

final class RegionPayloadValidator
{
    private const FIELDS = [
        'name', 'polygons', 'darknessMode', 'darknessValue',
        'disableGlobalIllumination', 'color', 'enabled', 'hidden',
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
        return $this->withRevision($payload, [], [], false);
    }

    private function validate(array $payload, bool $partial): array
    {
        $data = [];
        $errors = [];
        $allowed = $partial ? array_merge(self::FIELDS, ['revision']) : self::FIELDS;
        foreach (array_diff(array_keys($payload), $allowed) as $field) {
            $errors[$field] = 'Field is not writable.';
        }
        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);
            if ($name === '' || mb_strlen($name) > 150) $errors['name'] = 'Name is invalid.';
            else $data['name'] = $name;
        } elseif (!$partial) $data['name'] = 'Region';

        if (array_key_exists('polygons', $payload)) {
            $polygons = $this->polygons($payload['polygons'], $errors);
            if ($polygons !== null) $data['polygons_json'] = json_encode($polygons);
        } elseif (!$partial) $errors['polygons'] = 'At least one polygon is required.';

        if (array_key_exists('darknessMode', $payload)) {
            $mode = strtolower(trim((string) $payload['darknessMode']));
            if (!in_array($mode, ['add', 'subtract', 'override'], true)) {
                $errors['darknessMode'] = 'Darkness mode is invalid.';
            } else $data['darkness_mode'] = $mode;
        }
        if (array_key_exists('darknessValue', $payload)) {
            if (!is_numeric($payload['darknessValue']) || !is_finite((float) $payload['darknessValue'])
                || (float) $payload['darknessValue'] < 0 || (float) $payload['darknessValue'] > 1) {
                $errors['darknessValue'] = 'Darkness value must be between 0 and 1.';
            } else $data['darkness_value'] = (float) $payload['darknessValue'];
        }
        foreach (['disableGlobalIllumination' => 'disable_global_illumination',
            'enabled' => 'enabled', 'hidden' => 'hidden'] as $field => $column) {
            if (!array_key_exists($field, $payload)) continue;
            $value = filter_var($payload[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === null) $errors[$field] = 'A boolean is required.';
            else $data[$column] = $value ? 1 : 0;
        }
        if (array_key_exists('color', $payload)) {
            $color = strtoupper(trim((string) $payload['color']));
            if (!preg_match('/^#[0-9A-F]{6}([0-9A-F]{2})?$/', $color)) {
                $errors['color'] = 'Color is invalid.';
            } else $data['color'] = $color;
        }
        if (!$partial) $data += [
            'darkness_mode' => 'override', 'darkness_value' => 0,
            'disable_global_illumination' => 0, 'color' => '#8B5CF6',
            'enabled' => 1, 'hidden' => 0,
        ];
        return $partial ? $this->withRevision($payload, $data, $errors) : [
            'valid' => !$errors, 'data' => $data, 'errors' => $errors,
        ];
    }

    private function polygons($value, array &$errors): ?array
    {
        if (!is_array($value) || $value === [] || count($value) > 32) {
            $errors['polygons'] = 'Between 1 and 32 polygons are required.';
            return null;
        }
        $result = [];
        $total = 0;
        foreach ($value as $polygonIndex => $polygon) {
            if (!is_array($polygon) || count($polygon) < 3 || count($polygon) > 1000) {
                $errors['polygons'] = "Polygon {$polygonIndex} must have between 3 and 1000 points.";
                return null;
            }
            $points = [];
            foreach ($polygon as $point) {
                if (!is_array($point) || !isset($point['x'], $point['y'])
                    || !is_numeric($point['x']) || !is_numeric($point['y'])
                    || !is_finite((float) $point['x']) || !is_finite((float) $point['y'])
                    || abs((float) $point['x']) > 1000000 || abs((float) $point['y']) > 1000000) {
                    $errors['polygons'] = 'Every polygon point must contain finite x and y coordinates.';
                    return null;
                }
                $points[] = ['x' => (float) $point['x'], 'y' => (float) $point['y']];
            }
            $total += count($points);
            $result[] = $points;
        }
        if ($total > 5000) {
            $errors['polygons'] = 'A region cannot contain more than 5000 points.';
            return null;
        }
        return $result;
    }

    private function withRevision(
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
