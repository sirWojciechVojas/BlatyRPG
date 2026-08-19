<?php

namespace App\Services\Chat;

class CampaignChatMessageValidator
{
    public const MAX_LENGTH = 2000;

    public function validate(array $payload): array
    {
        $allowed = ['body', 'clientNonce', 'client_nonce'];
        $errors = [];
        foreach (array_diff(array_keys($payload), $allowed) as $field) {
            $errors[$field] = 'This field is not accepted.';
        }

        $body = $payload['body'] ?? null;
        if (!is_string($body)) {
            $errors['body'] = 'Message body must be a string.';
            $body = '';
        } else {
            $body = trim(str_replace(["\r\n", "\r"], "\n", $body));
            if ($body === '') {
                $errors['body'] = 'Message body cannot be empty.';
            } elseif (mb_strlen($body, 'UTF-8') > self::MAX_LENGTH) {
                $errors['body'] = 'Message body is too long.';
            } elseif (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $body)) {
                $errors['body'] = 'Message body contains unsupported control characters.';
            }
        }

        $nonce = $this->alias($payload, 'clientNonce', 'client_nonce', $errors);
        if ($nonce === null) {
            $errors['clientNonce'] = 'Client nonce is required.';
        } elseif (!is_string($nonce) || !preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $nonce
        )) {
            $errors['clientNonce'] = 'Client nonce must be a UUID.';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'data' => [
                'body' => $body,
                'client_nonce' => is_string($nonce) ? strtolower($nonce) : null,
            ],
        ];
    }

    private function alias(array $payload, string $camel, string $snake, array &$errors)
    {
        if (array_key_exists($camel, $payload) && array_key_exists($snake, $payload)
            && $payload[$camel] !== $payload[$snake]) {
            $errors[$camel] = 'Conflicting aliases were provided.';
        }
        return $payload[$camel] ?? $payload[$snake] ?? null;
    }
}
