<?php

namespace App\Services\Magic;

/** Reads the currently supported character JSON shapes without creating a second stat source. */
final class CharacterMagicData
{
    private const MAGIC_PATHS = [
        ['attributes', 'actual', 'mag'],
        ['attributes', 'actual', 'magic'],
        ['stats', 'mag'],
        ['stats', 'magic'],
        ['mag', 'cur'],
        ['magic', 'cur'],
        ['mag'],
        ['magic'],
    ];

    private const WILLPOWER_PATHS = [
        ['attributes', 'actual', 'sw'],
        ['attributes', 'actual', 'willpower'],
        ['stats', 'sw'],
        ['stats', 'willpower'],
        ['sw', 'cur'],
        ['willpower', 'cur'],
        ['sw'],
        ['willpower'],
    ];

    private const EXPERIENCE_PATHS = [
        ['experience', 'current'],
        ['experience', 'available'],
        ['attributes', 'exp', 'current'],
        ['experience_current'],
        ['experienceCurrent'],
    ];

    public static function decode($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function magic(array $data): int
    {
        return max(0, (int) self::first($data, self::MAGIC_PATHS, 0));
    }

    public static function willpower(array $data): int
    {
        return max(0, min(100, (int) self::first($data, self::WILLPOWER_PATHS, 0)));
    }

    public static function experience(array $data): array
    {
        foreach (self::EXPERIENCE_PATHS as $path) {
            $value = self::at($data, $path);
            if ($value !== null && is_numeric($value)) {
                return ['available' => max(0, (int) $value), 'path' => $path];
            }
        }
        return ['available' => 0, 'path' => self::EXPERIENCE_PATHS[0]];
    }

    public static function spendExperience(array &$data, int $amount): int
    {
        $experience = self::experience($data);
        if ($amount < 0 || $experience['available'] < $amount) {
            throw new \InvalidArgumentException('Insufficient experience.');
        }
        $balance = $experience['available'] - $amount;
        self::set($data, $experience['path'], $balance);
        return $balance;
    }

    public static function canSpeak(array $data): bool
    {
        if (($data['magic']['canSpeak'] ?? true) === false) {
            return false;
        }
        $conditions = array_merge((array) ($data['conditions'] ?? []), (array) ($data['statuses'] ?? []));
        foreach ($conditions as $condition) {
            $name = is_array($condition) ? ($condition['code'] ?? $condition['name'] ?? '') : $condition;
            if (in_array(mb_strtolower(trim((string) $name)), ['mute', 'silenced', 'niemy', 'uciszony'], true)) {
                return false;
            }
        }
        return true;
    }

    private static function first(array $data, array $paths, $fallback)
    {
        foreach ($paths as $path) {
            $value = self::at($data, $path);
            if ($value !== null && $value !== '' && is_numeric($value)) {
                return $value;
            }
        }
        return $fallback;
    }

    private static function at(array $data, array $path)
    {
        $cursor = $data;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }
        return $cursor;
    }

    private static function set(array &$data, array $path, int $value): void
    {
        $cursor =& $data;
        foreach ($path as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor =& $cursor[$segment];
        }
        $cursor = $value;
    }
}
