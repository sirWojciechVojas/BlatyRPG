<?php

namespace App\Services\Token;

final class TokenVisionValidator
{
    public static function validate($value): array
    {
        if (!is_array($value)) return ['valid' => false, 'error' => 'Vision must be an object.'];
        $allowed = [
            'enabled', 'range', 'angle', 'direction', 'minimumRadius',
            'darkvision', 'darkvisionRange', 'limitByLight', 'constrainedByWalls',
            'showShape', 'shapeBorderColor', 'shapeBorderOpacity',
            'shapeFillColor', 'shapeFillOpacity',
        ];
        if (array_diff(array_keys($value), $allowed)) {
            return ['valid' => false, 'error' => 'Vision contains unsupported fields.'];
        }
        $data = [];
        foreach (['enabled', 'darkvision', 'limitByLight', 'constrainedByWalls', 'showShape'] as $field) {
            if (!array_key_exists($field, $value)) continue;
            $boolean = filter_var($value[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($boolean === null) return ['valid' => false, 'error' => "Vision {$field} must be boolean."];
            $data[$field] = $boolean;
        }
        foreach (['shapeBorderOpacity', 'shapeFillOpacity'] as $field) {
            if (!array_key_exists($field, $value)) continue;
            if (!is_numeric($value[$field]) || !is_finite((float) $value[$field])) {
                return ['valid' => false, 'error' => "Vision {$field} must be a finite number."];
            }
            $number = (float) $value[$field];
            if ($number < 0 || $number > 1) {
                return ['valid' => false, 'error' => "Vision {$field} is outside the supported range."];
            }
            $data[$field] = $number;
        }
        foreach (['shapeBorderColor', 'shapeFillColor'] as $field) {
            if (!array_key_exists($field, $value)) continue;
            $candidate = strtolower(trim((string) $value[$field]));
            if (!preg_match('/^#[0-9a-f]{6}$/', $candidate)) {
                return ['valid' => false, 'error' => "Vision {$field} must be a hex color."];
            }
            $data[$field] = $candidate;
        }
        foreach (['range' => [0, 100000], 'minimumRadius' => [0, 100000],
            'darkvisionRange' => [0, 100000], 'angle' => [1, 360]] as $field => $range) {
            if (!array_key_exists($field, $value)) continue;
            if (!is_numeric($value[$field]) || !is_finite((float) $value[$field])) {
                return ['valid' => false, 'error' => "Vision {$field} must be a finite number."];
            }
            $number = (float) $value[$field];
            if ($number < $range[0] || $number > $range[1]) {
                return ['valid' => false, 'error' => "Vision {$field} is outside the supported range."];
            }
            $data[$field] = $number;
        }
        if (array_key_exists('direction', $value)) {
            if (!is_numeric($value['direction']) || !is_finite((float) $value['direction'])) {
                return ['valid' => false, 'error' => 'Vision direction must be a finite number.'];
            }
            $direction = fmod((float) $value['direction'], 360.0);
            $data['direction'] = $direction < 0 ? $direction + 360.0 : $direction;
        }
        $data += [
            'enabled' => false, 'range' => 600.0, 'angle' => 360.0,
            'minimumRadius' => 0.0, 'darkvision' => false,
            'darkvisionRange' => 0.0, 'limitByLight' => true,
            'constrainedByWalls' => true,
            'showShape' => false, 'shapeBorderColor' => '#65d7ff',
            'shapeBorderOpacity' => 0.8, 'shapeFillColor' => '#65d7ff',
            'shapeFillOpacity' => 0.12,
        ];
        return ['valid' => true, 'data' => $data];
    }
}
