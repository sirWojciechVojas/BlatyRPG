<?php

namespace App\Services\Token;

final class TokenMovementRoute
{
    public static function validate($value): array
    {
        if ($value === null) return [];
        if (!is_array($value) || count($value) > 20) {
            throw new TokenException('validation_failed', 'Movement route is invalid.', 422,
                ['waypoints' => 'Provide at most 20 route points.']);
        }
        $result = [];
        foreach ($value as $point) {
            if (!is_array($point) || !self::coordinate($point['x'] ?? null)
                || !self::coordinate($point['y'] ?? null)) {
                throw new TokenException('validation_failed', 'Movement route is invalid.', 422,
                    ['waypoints' => 'Every route point requires finite x and y coordinates.']);
            }
            $result[] = ['x' => (float) $point['x'], 'y' => (float) $point['y']];
        }
        return $result;
    }

    private static function coordinate($value): bool
    {
        return is_numeric($value) && is_finite((float) $value)
            && abs((float) $value) <= 1000000;
    }
}
