<?php

namespace App\Services\Authorization;

final class AccessLevel
{
    public const NONE = 'none';
    public const LIMITED = 'limited';
    public const OBSERVER = 'observer';
    public const OWNER = 'owner';

    private const WEIGHTS = [
        self::NONE => 0,
        self::LIMITED => 10,
        self::OBSERVER => 20,
        self::OWNER => 30,
    ];

    public static function normalize($level): ?string
    {
        $value = strtolower(trim((string) $level));
        return array_key_exists($value, self::WEIGHTS) ? $value : null;
    }

    public static function allows($actual, $required): bool
    {
        $actual = self::normalize($actual);
        $required = self::normalize($required);
        return $actual !== null && $required !== null
            && self::WEIGHTS[$actual] >= self::WEIGHTS[$required];
    }

    public static function values(): array
    {
        return array_keys(self::WEIGHTS);
    }
}
