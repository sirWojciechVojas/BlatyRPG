<?php

namespace App\Services\Token;

final class TokenDatabasePayload
{
    private const JSON_FIELDS = [
        'visible_to_json', 'controlled_by_json', 'editable_by_json',
        'observer_by_json', 'bars_json', 'statuses_json',
        'vision_json', 'light_json',
    ];

    public static function encode(array $data): array
    {
        foreach (self::JSON_FIELDS as $field) {
            if (!array_key_exists($field, $data) || !is_array($data[$field])) continue;
            $encoded = json_encode(
                $data[$field],
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
            if ($encoded === false) {
                throw new TokenException(
                    'validation_failed',
                    'Token JSON data could not be encoded.',
                    422,
                    [$field => 'Value must be valid JSON.']
                );
            }
            $data[$field] = $encoded;
        }
        return $data;
    }
}
