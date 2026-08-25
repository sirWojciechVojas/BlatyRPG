<?php

namespace App\Services\Token;

final class TokenMovementResource
{
    public static function barIndex(array $resources): ?int
    {
        $normalized = TokenResourceValidator::stored($resources);
        foreach ($normalized['bars'] as $index => $bar) {
            if (!empty($bar['movementSource'])) return $index;
        }
        return null;
    }

    public static function applyBubbleInputs(array $previous, array $resources): array
    {
        $previous = TokenResourceValidator::stored($previous);
        $resources = TokenResourceValidator::stored($resources);
        foreach ($resources['bubbles'] as $index => $bubble) {
            $barIndex = $bubble['linkedBarIndex'];
            if ($barIndex === null) continue;
            $bubbleChanged = $bubble['value'] !== $previous['bubbles'][$index]['value'];
            $barChanged = $resources['bars'][$barIndex]['value']
                !== $previous['bars'][$barIndex]['value'];
            if ($bubbleChanged && !$barChanged) {
                $resources['bars'][$barIndex]['value'] = $bubble['value'];
            }
        }
        return self::syncBubbles($resources);
    }

    public static function fromResources(
        array $resources,
        float $fallbackRange,
        float $fallbackSpent
    ): array {
        $resources = TokenResourceValidator::stored($resources);
        $index = self::barIndex($resources);
        if ($index === null) return [
            'resources' => self::syncBubbles($resources),
            'range' => max(0.0, $fallbackRange),
            'spent' => max(0.0, $fallbackSpent),
            'sourceIndex' => null,
        ];
        $range = self::movementNumber($resources['bars'][$index]['max']);
        $remaining = min($range, self::movementNumber($resources['bars'][$index]['value']));
        $resources['bars'][$index]['max'] = $range;
        $resources['bars'][$index]['value'] = $remaining;
        return [
            'resources' => self::syncBubbles($resources),
            'range' => $range,
            'spent' => round($range - $remaining, 3),
            'sourceIndex' => $index,
        ];
    }

    public static function fromMovement(array $resources, float $range, float $spent): array
    {
        $resources = TokenResourceValidator::stored($resources);
        $index = self::barIndex($resources);
        if ($index === null) return self::syncBubbles($resources);
        $range = self::movementNumber($range);
        $remaining = max(0.0, round($range - self::movementNumber($spent), 3));
        $resources['bars'][$index]['max'] = $range;
        $resources['bars'][$index]['value'] = $remaining;
        return self::syncBubbles($resources);
    }

    public static function controlsChanged(
        array $previous,
        array $resources,
        float $range,
        float $spent
    ): bool {
        $before = self::fromMovement($previous, $range, $spent);
        $afterInput = self::applyBubbleInputs($before, $resources);
        $after = self::fromResources($afterInput, $range, $spent);
        return self::controlProjection($before) !== self::controlProjection($after['resources'])
            || $after['range'] !== max(0.0, $range)
            || $after['spent'] !== max(0.0, $spent);
    }

    private static function syncBubbles(array $resources): array
    {
        foreach ($resources['bubbles'] as &$bubble) {
            $index = $bubble['linkedBarIndex'];
            if ($index !== null) $bubble['value'] = $resources['bars'][$index]['value'];
        }
        unset($bubble);
        return $resources;
    }

    private static function controlProjection(array $resources): array
    {
        $index = self::barIndex($resources);
        return [
            'index' => $index,
            'bar' => $index === null ? null : [
                'attributePath' => $resources['bars'][$index]['attributePath'],
                'maxAttributePath' => $resources['bars'][$index]['maxAttributePath'],
            ],
            'links' => array_map(
                static fn (array $bubble) => $index !== null
                    && $bubble['linkedBarIndex'] === $index ? $index : null,
                $resources['bubbles']
            ),
        ];
    }

    private static function movementNumber($value): float
    {
        return round(max(0.0, min(10000.0, (float) $value)), 3);
    }
}
