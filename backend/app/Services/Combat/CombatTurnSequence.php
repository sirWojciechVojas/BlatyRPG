<?php

namespace App\Services\Combat;

final class CombatTurnSequence
{
    public static function advance(int $round, int $index, int $count, int $direction): array
    {
        if ($count < 1) {
            throw new CombatException('combat_empty', 'Combat has no combatants.', 422);
        }
        $round = max(1, $round);
        $index = min($count - 1, max(0, $index));
        if ($direction > 0) {
            $next = ($index + 1) % $count;
            $newRound = $next === 0;
            return [
                'round' => $round + ($newRound ? 1 : 0),
                'turnIndex' => $next,
                'newRound' => $newRound,
            ];
        }
        $previous = ($index - 1 + $count) % $count;
        $previousRound = $index === 0 && $round > 1;
        return [
            'round' => $round - ($previousRound ? 1 : 0),
            'turnIndex' => $previous,
            'newRound' => false,
        ];
    }
}
