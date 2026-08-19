<?php

namespace App\Services\Character;

final class CharacterRevisionPolicy
{
    public function current(array $character): int
    {
        return max(1, (int) ($character['revision'] ?? 1));
    }

    public function assertExpected(
        array $character,
        ?int $expectedRevision,
        ?string $expectedUpdatedAt
    ): int {
        $current = $this->current($character);
        if ($expectedRevision !== null && $expectedRevision !== $current) {
            $this->throwConflict($current);
        }
        if ($expectedUpdatedAt !== null && !$this->sameTime(
            $expectedUpdatedAt,
            $character['updated_at'] ?? null
        )) {
            $this->throwConflict($current);
        }
        return $current;
    }

    public function nextTimestamp(array $character): string
    {
        $current = strtotime((string) ($character['updated_at'] ?? '')) ?: 0;
        return date('Y-m-d H:i:s', max(time(), $current + 1));
    }

    public function throwConflict(int $currentRevision): void
    {
        throw new CharacterException(
            'character_conflict',
            'Character was changed by another user. Reload it before saving.',
            409,
            ['currentRevision' => max(1, $currentRevision)]
        );
    }

    private function sameTime(string $expected, $current): bool
    {
        $expectedTime = strtotime($expected);
        $currentTime = strtotime((string) $current);
        return $expectedTime !== false
            && $currentTime !== false
            && $expectedTime === $currentTime;
    }
}
