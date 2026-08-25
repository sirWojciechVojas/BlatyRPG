<?php

namespace App\Services\Token;

final class TokenGridPositionService
{
    private const SQRT_THREE = 1.7320508075688772;

    public function snap(array $scene, array $position, float $width, float $height): array
    {
        $type = (string) ($scene['grid_type'] ?? $scene['gridType'] ?? 'square');
        if ($type === 'gridless') {
            return ['x' => (float) ($position['x'] ?? 0), 'y' => (float) ($position['y'] ?? 0)];
        }
        $size = max(1.0, min(1000.0, (float) ($scene['grid_size'] ?? $scene['gridSize'] ?? 100)));
        $offsetX = (float) ($scene['grid_offset_x'] ?? $scene['gridOffsetX'] ?? 0);
        $offsetY = (float) ($scene['grid_offset_y'] ?? $scene['gridOffsetY'] ?? 0);
        $center = [
            'x' => (float) ($position['x'] ?? 0) + $width / 2,
            'y' => (float) ($position['y'] ?? 0) + $height / 2,
        ];
        $center = $type === 'square'
            ? $this->squareCenter($center, $size, $offsetX, $offsetY)
            : $this->hexCenter($type, $center, $size, $offsetX, $offsetY);
        return [
            'x' => round($center['x'] - $width / 2, 3),
            'y' => round($center['y'] - $height / 2, 3),
        ];
    }

    private function squareCenter(array $point, float $size, float $offsetX, float $offsetY): array
    {
        return [
            'x' => $offsetX + ($this->nearest(($point['x'] - $offsetX - $size / 2) / $size) + 0.5) * $size,
            'y' => $offsetY + ($this->nearest(($point['y'] - $offsetY - $size / 2) / $size) + 0.5) * $size,
        ];
    }

    private function hexCenter(string $type, array $point, float $size, float $offsetX, float $offsetY): array
    {
        $x = $point['x'] - $offsetX;
        $y = $point['y'] - $offsetY;
        if ($type === 'hex_pointy') {
            $q = $x / $size - $y / ($size * self::SQRT_THREE);
            $r = 2 * $y / ($size * self::SQRT_THREE);
        } else {
            $q = 2 * $x / ($size * self::SQRT_THREE);
            $r = $y / $size - $x / ($size * self::SQRT_THREE);
        }
        [$q, $r] = $this->roundAxial($q, $r);
        if ($type === 'hex_pointy') {
            return [
                'x' => $offsetX + $size * ($q + $r / 2),
                'y' => $offsetY + $size * self::SQRT_THREE * $r / 2,
            ];
        }
        return [
            'x' => $offsetX + $size * self::SQRT_THREE * $q / 2,
            'y' => $offsetY + $size * ($r + $q / 2),
        ];
    }

    private function roundAxial(float $q, float $r): array
    {
        $x = $this->nearest($q);
        $z = $this->nearest($r);
        $y = $this->nearest(-$q - $r);
        $dx = abs($x - $q);
        $dy = abs($y + $q + $r);
        $dz = abs($z - $r);
        if ($dx > $dy && $dx > $dz) $x = -$y - $z;
        elseif ($dz > $dy) $z = -$x - $y;
        return [$x, $z];
    }

    private function nearest(float $value): int
    {
        return (int) floor($value + 0.5);
    }
}
