<?php

namespace App\Services\Magic;

use InvalidArgumentException;

/** Pure WFRP 2e casting resolver. It has no database or transport dependency. */
final class Wfrp2MagicResolver
{
    /** @var callable */
    private $roll;

    public function __construct(?callable $roll = null)
    {
        $this->roll = $roll ?: static fn (int $minimum, int $maximum): int => random_int($minimum, $maximum);
    }

    public function resolve(
        int $powerDiceCount,
        int $chaosDiceCount,
        int $castingNumber,
        int $modifier = 0
    ): array {
        if ($powerDiceCount < 1 || $powerDiceCount > 10) {
            throw new InvalidArgumentException('Power dice count must be between 1 and 10.');
        }
        if ($chaosDiceCount < 0 || $chaosDiceCount > 4) {
            throw new InvalidArgumentException('Chaos dice count must be between 0 and 4.');
        }
        if ($castingNumber < 1) {
            throw new InvalidArgumentException('Casting number must be positive.');
        }

        $powerDice = $this->rollDice($powerDiceCount);
        $chaosDice = $this->rollDice($chaosDiceCount);
        return $this->resolveResults($powerDice, $chaosDice, $castingNumber, $modifier);
    }

    /**
     * Resolves supplied raw results. This public seam makes the rule edge cases
     * deterministic in tests without allowing an API client to submit results.
     */
    public function resolveResults(
        array $powerDice,
        array $chaosDice,
        int $castingNumber,
        int $modifier = 0
    ): array {
        $this->assertDice($powerDice, 1, 10, 'power');
        $this->assertDice($chaosDice, 0, 4, 'chaos');
        if ($castingNumber < 1) {
            throw new InvalidArgumentException('Casting number must be positive.');
        }

        $automaticFailure = count($powerDice) > 0
            && count(array_filter($powerDice, static fn (int $die): bool => $die === 1)) === count($powerDice);
        $powerTotal = array_sum($powerDice) + $modifier;
        $manifestations = $this->manifestations(array_merge($powerDice, $chaosDice));
        $needsGmDecision = count(array_filter(
            $manifestations,
            static fn (array $item): bool => $item['severity'] === 'undefined'
        )) > 0;

        $dice = [];
        foreach ($powerDice as $index => $result) {
            $dice[] = [
                'role' => 'power', 'order' => $index + 1, 'result' => $result,
                'includedInPower' => true, 'includedInCurse' => true,
                'includedInAutomaticFailure' => true,
            ];
        }
        foreach ($chaosDice as $index => $result) {
            $dice[] = [
                'role' => 'chaos', 'order' => $index + 1, 'result' => $result,
                'includedInPower' => false, 'includedInCurse' => true,
                'includedInAutomaticFailure' => false,
            ];
        }

        return [
            'powerDice' => array_values($powerDice),
            'chaosDice' => array_values($chaosDice),
            'dice' => $dice,
            'modifier' => $modifier,
            'powerTotal' => $powerTotal,
            'castingNumber' => $castingNumber,
            'automaticFailure' => $automaticFailure,
            'willpowerTestRequired' => $automaticFailure,
            'spellSucceeded' => !$automaticFailure && $powerTotal >= $castingNumber,
            'manifestations' => $manifestations,
            'needsGmDecision' => $needsGmDecision,
        ];
    }

    private function rollDice(int $count): array
    {
        $results = [];
        for ($index = 0; $index < $count; $index++) {
            $results[] = (int) ($this->roll)(1, 10);
        }
        return $results;
    }

    private function manifestations(array $dice): array
    {
        $counts = array_count_values($dice);
        ksort($counts, SORT_NUMERIC);
        $manifestations = [];
        foreach ($counts as $face => $count) {
            if ($count < 2) {
                continue;
            }
            $severity = [
                2 => 'minor',
                3 => 'major',
                4 => 'catastrophic',
            ][$count] ?? 'undefined';
            $manifestations[] = [
                'face' => (int) $face,
                'matchingDice' => (int) $count,
                'severity' => $severity,
                'requiresGmResolution' => $severity === 'undefined',
            ];
        }
        return $manifestations;
    }

    private function assertDice(array $dice, int $minimumCount, int $maximumCount, string $role): void
    {
        if (count($dice) < $minimumCount || count($dice) > $maximumCount) {
            throw new InvalidArgumentException("Invalid {$role} dice count.");
        }
        foreach ($dice as $die) {
            if (!is_int($die) || $die < 1 || $die > 10) {
                throw new InvalidArgumentException("Invalid {$role} die result.");
            }
        }
    }
}
