<?php

namespace App\Services\Token;

final class TokenMovementBudget
{
    public static function remaining(array $token): float
    {
        $range = max(0.0, (float) ($token['movement_range'] ?? 0));
        $spent = max(0.0, (float) ($token['movement_spent'] ?? 0));
        return max(0.0, round($range - $spent, 3));
    }

    public static function assertAvailable(array $token): void
    {
        if (self::remaining($token) > 0.0005) return;
        $range = max(0.0, (float) ($token['movement_range'] ?? 0));
        $spent = max(0.0, (float) ($token['movement_spent'] ?? 0));
        throw new TokenException('movement_points_depleted',
            'Movement is blocked until the game master assigns movement points.', 422, [
                'spent' => $spent, 'range' => $range, 'remaining' => 0,
            ]);
    }
}
