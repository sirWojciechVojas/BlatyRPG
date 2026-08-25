<?php

namespace App\Services\Token;

final class TokenMovementCost
{
    private const SQRT_THREE = 1.7320508075688772;

    public static function route(array $scene, array $points): float
    {
        if (count($points) < 2) return 0.0;
        $cost = 0.0;
        for ($index = 1; $index < count($points); $index++) {
            $cost += self::segment($scene, $points[$index - 1], $points[$index]);
        }
        return round($cost, 3);
    }

    private static function segment(array $scene, array $start, array $end): float
    {
        $size = max(1.0, (float) ($scene['grid_size'] ?? $scene['gridSize'] ?? 100));
        $type = (string) ($scene['grid_type'] ?? $scene['gridType'] ?? 'square');
        if ($type === 'gridless') {
            return hypot($end['x'] - $start['x'], $end['y'] - $start['y']) / $size;
        }
        $a = self::cell($scene, $start, $type, $size);
        $b = self::cell($scene, $end, $type, $size);
        if ($type === 'square') {
            return (float) max(abs($b['q'] - $a['q']), abs($b['r'] - $a['r']));
        }
        $dq = $b['q'] - $a['q'];
        $dr = $b['r'] - $a['r'];
        return (abs($dq) + abs($dr) + abs($dq + $dr)) / 2;
    }

    private static function cell(array $scene, array $point, string $type, float $size): array
    {
        $x = (float) $point['x'] - (float) ($scene['grid_offset_x'] ?? $scene['gridOffsetX'] ?? 0);
        $y = (float) $point['y'] - (float) ($scene['grid_offset_y'] ?? $scene['gridOffsetY'] ?? 0);
        if ($type === 'square') {
            return ['q' => self::nearest(($x - $size / 2) / $size),
                'r' => self::nearest(($y - $size / 2) / $size)];
        }
        if ($type === 'hex_pointy') {
            return self::roundAxial($x / $size - $y / ($size * self::SQRT_THREE),
                2 * $y / ($size * self::SQRT_THREE));
        }
        return self::roundAxial(2 * $x / ($size * self::SQRT_THREE),
            $y / $size - $x / ($size * self::SQRT_THREE));
    }

    private static function roundAxial(float $q, float $r): array
    {
        $x = self::nearest($q);
        $z = self::nearest($r);
        $y = self::nearest(-$q - $r);
        $dx = abs($x - $q);
        $dy = abs($y + $q + $r);
        $dz = abs($z - $r);
        if ($dx > $dy && $dx > $dz) $x = -$y - $z;
        elseif ($dz > $dy) $z = -$x - $y;
        return ['q' => $x, 'r' => $z];
    }

    private static function nearest(float $value): int
    {
        return (int) floor($value + 0.5);
    }
}
