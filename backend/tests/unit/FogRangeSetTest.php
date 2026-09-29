<?php

use App\Services\Fog\FogRangeSet;
use PHPUnit\Framework\TestCase;

final class FogRangeSetTest extends TestCase
{
    public function testItMergesAndSubtractsCompactRanges(): void
    {
        self::assertSame([[1, 8]], FogRangeSet::normalize([[5, 8], [1, 3], [4, 4]], 20));
        self::assertSame([[1, 2], [6, 8]], FogRangeSet::subtract([[1, 8]], [[3, 5]], 20));
    }

    public function testForcedHiddenRangesCanBeExcludedFromExploration(): void
    {
        $candidate = [[0, 9]];
        $hidden = [[3, 6]];
        self::assertSame([[0, 2], [7, 9]], FogRangeSet::subtract($candidate, $hidden, 10));
    }

    public function testServerVisibilityCanAuthorizeOnlyPartOfAClientPatch(): void
    {
        self::assertSame(
            [[2, 4], [8, 9]],
            FogRangeSet::intersect([[0, 9]], [[2, 4], [8, 12]], 10)
        );
    }
}
