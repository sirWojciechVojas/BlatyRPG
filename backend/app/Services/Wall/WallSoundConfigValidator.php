<?php

namespace App\Services\Wall;

final class WallSoundConfigValidator
{
    private const TRIGGERS = ['open', 'close', 'lock', 'lockedAttempt', 'proximityLoop'];
    private const GEOMETRIES = ['points', 'offsetLine'];

    public function validate(array $config): array
    {
        $errors = [];
        if (array_diff(array_keys($config), ['version', 'rules'])) {
            $errors['soundConfig'] = 'Sound configuration contains unknown fields.';
        }
        if (($config['version'] ?? null) !== 2 || !is_array($config['rules'] ?? null)) {
            $errors['soundConfig'] = 'Sound configuration version is invalid.';
            return ['valid' => false, 'config' => null, 'trackIds' => [], 'errors' => $errors];
        }
        if (count($config['rules']) > 16) {
            $errors['soundConfig.rules'] = 'At most 16 sound rules are allowed.';
        }
        $rules = [];
        $trackIds = [];
        $ids = [];
        foreach (array_slice($config['rules'], 0, 16) as $index => $rule) {
            $path = 'soundConfig.rules.' . $index;
            if (!is_array($rule)) {
                $errors[$path] = 'Sound rule must be an object.';
                continue;
            }
            $allowed = [
                'id', 'enabled', 'trigger', 'trackId', 'volume', 'fadeInMs',
                'fadeOutMs', 'range', 'zoneCount', 'geometry',
            ];
            if (array_diff(array_keys($rule), $allowed)) {
                $errors[$path] = 'Sound rule contains unknown fields.';
                continue;
            }
            $id = trim((string) ($rule['id'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $id) || isset($ids[$id])) {
                $errors[$path . '.id'] = 'Sound rule identifier is invalid or duplicated.';
            }
            $ids[$id] = true;
            $enabled = filter_var($rule['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($enabled === null) $errors[$path . '.enabled'] = 'Enabled must be a boolean.';
            $trigger = trim((string) ($rule['trigger'] ?? ''));
            if (!in_array($trigger, self::TRIGGERS, true)) {
                $errors[$path . '.trigger'] = 'Sound trigger is invalid.';
            }
            $trackId = filter_var($rule['trackId'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($trackId === false) $errors[$path . '.trackId'] = 'A library track is required.';
            $volume = $this->number($rule['volume'] ?? null, 0, 1, $path . '.volume', $errors);
            $fadeIn = $this->integer($rule['fadeInMs'] ?? null, 0, 10000, $path . '.fadeInMs', $errors);
            $fadeOut = $this->integer($rule['fadeOutMs'] ?? null, 0, 10000, $path . '.fadeOutMs', $errors);
            $range = $this->number($rule['range'] ?? null, 0.1, 100000, $path . '.range', $errors);
            $zoneCount = $this->integer($rule['zoneCount'] ?? null, 1, 12, $path . '.zoneCount', $errors);
            $geometry = $this->geometry($rule['geometry'] ?? null, $path . '.geometry', $errors);
            if (array_filter(array_keys($errors), static fn (string $key): bool => str_starts_with($key, $path))) {
                continue;
            }
            $trackIds[(int) $trackId] = (int) $trackId;
            $rules[] = [
                'id' => $id,
                'enabled' => (bool) $enabled,
                'trigger' => $trigger,
                'trackId' => (int) $trackId,
                'volume' => $volume,
                'fadeInMs' => $fadeIn,
                'fadeOutMs' => $fadeOut,
                'range' => $range,
                'zoneCount' => $zoneCount,
                'geometry' => $geometry,
            ];
        }
        return [
            'valid' => !$errors,
            'config' => !$errors ? ['version' => 2, 'rules' => $rules] : null,
            'trackIds' => array_values($trackIds),
            'errors' => $errors,
        ];
    }

    private function geometry($value, string $path, array &$errors): ?array
    {
        if (!is_array($value) || array_diff(array_keys($value), ['mode', 'points', 'offset'])) {
            $errors[$path] = 'Sound geometry is invalid.';
            return null;
        }
        $mode = trim((string) ($value['mode'] ?? ''));
        if (!in_array($mode, self::GEOMETRIES, true)) {
            $errors[$path . '.mode'] = 'Sound geometry mode is invalid.';
        }
        $points = $value['points'] ?? null;
        if (!is_array($points) || !$points || count($points) > 16) {
            $errors[$path . '.points'] = 'Provide between 1 and 16 wall points.';
            $points = [];
        }
        $normalizedPoints = [];
        foreach ($points as $index => $point) {
            $number = $this->number($point, 0, 1, $path . '.points.' . $index, $errors);
            if ($number !== null) $normalizedPoints[] = $number;
        }
        $offset = $this->number($value['offset'] ?? null, -100000, 100000, $path . '.offset', $errors);
        return [
            'mode' => $mode,
            'points' => array_values(array_unique($normalizedPoints, SORT_REGULAR)),
            'offset' => $offset,
        ];
    }

    private function number($value, float $minimum, float $maximum, string $path, array &$errors): ?float
    {
        if (!is_numeric($value) || !is_finite((float) $value)
            || (float) $value < $minimum || (float) $value > $maximum) {
            $errors[$path] = 'Numeric value is outside the allowed range.';
            return null;
        }
        return (float) $value;
    }

    private function integer($value, int $minimum, int $maximum, string $path, array &$errors): ?int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $minimum, 'max_range' => $maximum],
        ]);
        if ($number === false) {
            $errors[$path] = 'Integer value is outside the allowed range.';
            return null;
        }
        return (int) $number;
    }
}

