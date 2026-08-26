<?php

namespace App\Services\Combat;

final class CombatMovementValue
{
    public static function parse($value): float
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            throw new CombatException('movement_invalid', 'Movement value is invalid.', 422);
        }
        if (!is_numeric($value) || !is_finite((float) $value)) {
            throw new CombatException('movement_invalid', 'Movement value is invalid.', 422);
        }
        $number = (float) $value;
        if ($number < 0 || $number > 10000) {
            throw new CombatException('movement_invalid', 'Movement value is invalid.', 422);
        }
        return $number;
    }
}
