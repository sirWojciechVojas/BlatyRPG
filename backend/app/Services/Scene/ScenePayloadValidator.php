<?php

namespace App\Services\Scene;

class ScenePayloadValidator
{
    private const MUTABLE_FIELDS = [
        'name', 'description', 'background_url', 'width', 'height', 'padding',
        'background_color', 'grid_type', 'grid_size', 'grid_distance', 'grid_unit',
        'grid_offset_x', 'grid_offset_y', 'grid_color', 'grid_opacity', 'is_visible',
        'sort_order', 'darkness_level', 'global_illumination', 'global_light_level',
        'fog_exploration', 'fog_enabled', 'dynamic_vision', 'exploration_memory',
        'fog_unexplored_color', 'fog_unexplored_opacity', 'fog_explored_opacity',
        'fog_edge_softness', 'fog_update_during_drag',
        'global_illumination_threshold', 'fog_exploration_mode',
        'fog_explored_color', 'fog_exploration_image',
        'darkness_transition_from', 'darkness_transition_to',
        'darkness_transition_started_at', 'darkness_transition_duration',
    ];

    public function validateCreate(array $payload): array
    {
        return $this->validate($payload, false);
    }

    public function validateUpdate(array $payload): array
    {
        return $this->validate($payload, true);
    }

    public function validateDuplicate(array $payload): array
    {
        $result = $this->validateCreate(['name' => $payload['name'] ?? null]);
        foreach (array_diff(array_keys($payload), ['name']) as $field) {
            $result['errors'][$field] = 'This field is not supported.';
        }
        $result['valid'] = $result['errors'] === [];
        return $result;
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
        foreach (['description' => 10000, 'background_url' => 2048,
            'fog_exploration_image' => 2048] as $field => $max) {
            if (array_key_exists($field, $payload)) {
                $this->stringField($payload, $field, 0, $max, true, $data, $errors);
            }
        }
        foreach (['grid_unit' => 32] as $field => $max) {
            if (array_key_exists($field, $payload)) {
                $this->stringField($payload, $field, 1, $max, false, $data, $errors);
            }
        }
        foreach (['background_url', 'fog_exploration_image'] as $assetField) {
            if (isset($data[$assetField]) && !$this->safeAssetUrl($data[$assetField])) {
                $errors[$assetField] = 'Only relative, http and https asset URLs are allowed.';
                unset($data[$assetField]);
            }
        }

        $integers = [
            'width' => [256, 50000], 'height' => [256, 50000], 'padding' => [0, 5000],
            'grid_size' => [1, 1000], 'sort_order' => [-100000, 100000],
            'darkness_transition_duration' => [0, 3600000],
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
            'darkness_level' => [0, 1], 'global_light_level' => [0, 1],
            'grid_distance' => [0.01, 1000000],
            'grid_offset_x' => [-50000, 50000],
            'grid_offset_y' => [-50000, 50000], 'grid_opacity' => [0, 1],
            'fog_unexplored_opacity' => [0, 1], 'fog_explored_opacity' => [0, 1],
            'fog_edge_softness' => [0, 200],
            'global_illumination_threshold' => [0, 1],
            'darkness_transition_from' => [0, 1], 'darkness_transition_to' => [0, 1],
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
        if (array_key_exists('fog_exploration_mode', $payload)) {
            $mode = strtolower(trim((string) $payload['fog_exploration_mode']));
            if (!in_array($mode, ['none', 'individual', 'shared'], true)) {
                $errors['fog_exploration_mode'] = 'Unsupported exploration mode.';
            } else $data['fog_exploration_mode'] = $mode;
        }
        if (array_key_exists('darkness_transition_started_at', $payload)) {
            $startedAt = $payload['darkness_transition_started_at'];
            if ($startedAt === null || $startedAt === '') $data['darkness_transition_started_at'] = null;
            elseif (!is_string($startedAt) || strtotime($startedAt) === false) {
                $errors['darkness_transition_started_at'] = 'Transition start must be a valid date.';
            } else $data['darkness_transition_started_at'] = date('Y-m-d H:i:s', strtotime($startedAt));
        }
        foreach (['background_color', 'grid_color', 'fog_unexplored_color',
            'fog_explored_color'] as $field) {
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
        foreach (['is_visible', 'global_illumination', 'fog_exploration', 'fog_enabled',
            'dynamic_vision', 'exploration_memory', 'fog_update_during_drag'] as $field) {
            if (!array_key_exists($field, $payload)) continue;
            $value = filter_var($payload[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === null) {
                $errors[$field] = 'Value must be boolean.';
            } else {
                $data[$field] = $value ? 1 : 0;
            }
        }

        if (array_key_exists('global_light_level', $data)
            && !array_key_exists('darkness_level', $data)) {
            $data['darkness_level'] = 1 - $data['global_light_level'];
            $data['global_illumination'] = 0;
        } elseif (array_key_exists('darkness_level', $data)
            && !array_key_exists('global_light_level', $data)) {
            $legacyMultiplier = !empty($data['global_illumination']) ? 0.18 : 1;
            $data['global_light_level'] = 1 - $data['darkness_level'] * $legacyMultiplier;
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
