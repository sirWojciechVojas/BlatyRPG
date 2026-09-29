<?php

namespace App\Services\Token;

final class TokenStatusValidator
{
    public static function validate($value): array
    {
        if (!is_array($value) || count($value) > 32) {
            return self::invalid('Statuses must be an array with at most 32 entries.');
        }
        $statuses = [];
        foreach ($value as $status) {
            if (!is_string($status)) {
                return self::invalid('Every quick status must be a code.');
            }
            $code = strtolower(trim($status));
            if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $code)) {
                return self::invalid('Status code is invalid.');
            }
            $statuses[$code] = $code;
        }
        return [
            'valid' => true,
            'data' => array_values($statuses),
            'error' => null,
        ];
    }

    private static function invalid(string $message): array
    {
        return ['valid' => false, 'data' => null, 'error' => $message];
    }
}
