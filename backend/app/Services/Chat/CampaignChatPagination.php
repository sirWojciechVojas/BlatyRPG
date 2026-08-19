<?php

namespace App\Services\Chat;

class CampaignChatPagination
{
    public const DEFAULT_LIMIT = 50;
    public const MAX_LIMIT = 100;

    public function parse(array $query): array
    {
        $errors = [];
        $after = $this->cursor($query, 'afterId', 'after_id', $errors);
        $before = $this->cursor($query, 'beforeId', 'before_id', $errors);
        if ($after !== null && $before !== null) {
            $errors['cursor'] = 'Use either beforeId or afterId, not both.';
        }

        $limit = $query['limit'] ?? self::DEFAULT_LIMIT;
        if (filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $errors['limit'] = 'Limit must be a positive integer.';
            $limit = self::DEFAULT_LIMIT;
        }
        $limit = min((int) $limit, self::MAX_LIMIT);

        if ($errors) {
            throw new CampaignChatException(
                'invalid_query',
                'Chat pagination query is invalid.',
                422,
                $errors
            );
        }
        return ['afterId' => $after, 'beforeId' => $before, 'limit' => $limit];
    }

    private function cursor(array $query, string $camel, string $snake, array &$errors): ?int
    {
        if (array_key_exists($camel, $query) && array_key_exists($snake, $query)
            && (string) $query[$camel] !== (string) $query[$snake]) {
            $errors[$camel] = 'Conflicting aliases were provided.';
            return null;
        }
        $raw = $query[$camel] ?? $query[$snake] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            $errors[$camel] = 'Cursor must be a positive integer.';
            return null;
        }
        return (int) $value;
    }
}
