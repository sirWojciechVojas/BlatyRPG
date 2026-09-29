<?php

namespace App\Services\Token;

final class TokenGroupMovementPayload
{
    public static function validate($value): array
    {
        if (!is_array($value) || count($value) < 2 || count($value) > 50) {
            throw self::invalid('Select between 2 and 50 token moves.');
        }
        $result = [];
        $ids = [];
        foreach ($value as $move) {
            if (!is_array($move) || array_diff(
                array_keys($move), ['tokenId', 'revision', 'x', 'y', 'waypoints']
            )) {
                throw self::invalid('Every group entry must contain only movement fields.');
            }
            $tokenId = self::positiveId($move['tokenId'] ?? null);
            $revision = self::positiveId($move['revision'] ?? null);
            if (isset($ids[$tokenId])) {
                throw self::invalid('A token can occur only once in a group move.');
            }
            $ids[$tokenId] = true;
            $result[] = [
                'tokenId' => $tokenId,
                'revision' => $revision,
                'x' => self::coordinate($move['x'] ?? null),
                'y' => self::coordinate($move['y'] ?? null),
                'waypoints' => TokenMovementRoute::validate($move['waypoints'] ?? []),
            ];
        }
        return $result;
    }

    private static function positiveId($value): int
    {
        $result = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($result === false) throw self::invalid('Token ids and revisions must be positive.');
        return (int) $result;
    }

    private static function coordinate($value): float
    {
        if (!is_numeric($value) || !is_finite((float) $value)
            || abs((float) $value) > 1000000) {
            throw self::invalid('Token coordinates must be finite.');
        }
        return (float) $value;
    }

    private static function invalid(string $message): TokenException
    {
        return new TokenException('validation_failed', $message, 422, [
            'moves' => $message,
        ]);
    }
}
