<?php

namespace App\Services\Fog;

final class FogRangeSet
{
    public static function normalize($value, int $maximum): array
    {
        if (!is_array($value) || $maximum < 1) return [];
        $ranges = [];
        foreach ($value as $range) {
            if (!is_array($range) || count($range) !== 2) continue;
            $start = filter_var($range[0], FILTER_VALIDATE_INT);
            $end = filter_var($range[1], FILTER_VALIDATE_INT);
            if ($start === false || $end === false) continue;
            $start = max(0, min($maximum - 1, (int) $start));
            $end = max(0, min($maximum - 1, (int) $end));
            if ($end < $start) [$start, $end] = [$end, $start];
            $ranges[] = [$start, $end];
        }
        usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($ranges as $range) {
            $last = count($merged) - 1;
            if ($last >= 0 && $range[0] <= $merged[$last][1] + 1) {
                $merged[$last][1] = max($merged[$last][1], $range[1]);
            } else $merged[] = $range;
        }
        return $merged;
    }

    public static function union(array $left, array $right, int $maximum): array
    {
        return self::normalize(array_merge($left, $right), $maximum);
    }

    public static function intersect(array $left, array $right, int $maximum): array
    {
        $left = self::normalize($left, $maximum);
        $right = self::normalize($right, $maximum);
        $result = [];
        $leftIndex = 0;
        $rightIndex = 0;
        while ($leftIndex < count($left) && $rightIndex < count($right)) {
            [$leftStart, $leftEnd] = $left[$leftIndex];
            [$rightStart, $rightEnd] = $right[$rightIndex];
            $start = max($leftStart, $rightStart);
            $end = min($leftEnd, $rightEnd);
            if ($start <= $end) $result[] = [$start, $end];
            if ($leftEnd < $rightEnd) $leftIndex++;
            else $rightIndex++;
        }
        return $result;
    }

    public static function subtract(array $source, array $removed, int $maximum): array
    {
        $source = self::normalize($source, $maximum);
        foreach (self::normalize($removed, $maximum) as [$cutStart, $cutEnd]) {
            $next = [];
            foreach ($source as [$start, $end]) {
                if ($cutEnd < $start || $cutStart > $end) { $next[] = [$start, $end]; continue; }
                if ($cutStart > $start) $next[] = [$start, $cutStart - 1];
                if ($cutEnd < $end) $next[] = [$cutEnd + 1, $end];
            }
            $source = $next;
        }
        return $source;
    }
}
