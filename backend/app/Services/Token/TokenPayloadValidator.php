<?php

namespace App\Services\Token;

final class TokenPayloadValidator
{
    private const TEXT_LIMITS = ['name' => 150, 'imageUrl' => 2048];
    private const NUMBERS = ['x', 'y', 'width', 'height', 'rotation', 'elevation'];
    private const DISPOSITIONS = ['friendly', 'neutral', 'hostile', 'secret'];
    private const WRITABLE = [
        'characterId', 'name', 'imageUrl', 'x', 'y', 'width', 'height',
        'rotation', 'elevation', 'disposition', 'hidden', 'locked',
    ];

    public function create(array $payload): array
    {
        $result = $this->fields($payload, false);
        $this->unknownFields($payload, self::WRITABLE, $result);
        if (!isset($result['data']['name']) || $result['data']['name'] === '') {
            $result['errors']['name'] = 'Name is required.';
        }
        return $this->result($result);
    }

    public function update(array $payload): array
    {
        $result = $this->fields($payload, true);
        $this->unknownFields($payload, array_merge(self::WRITABLE, ['revision']), $result);
        $revision = filter_var($payload['revision'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($revision === false) $result['errors']['revision'] = 'Revision is required.';
        else $result['revision'] = (int) $revision;
        if (!$result['data']) $result['errors']['payload'] = 'At least one token field is required.';
        return $this->result($result);
    }

    public function deletion(array $payload): array
    {
        $revision = filter_var($payload['revision'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        return $revision === false
            ? ['valid' => false, 'errors' => ['revision' => 'Revision is required.']]
            : ['valid' => true, 'revision' => (int) $revision, 'errors' => []];
    }

    private function fields(array $payload, bool $partial): array
    {
        $result = ['data' => [], 'errors' => []];
        foreach (self::TEXT_LIMITS as $field => $limit) {
            if (!array_key_exists($field, $payload)) continue;
            $value = trim((string) $payload[$field]);
            if ($field === 'name' && $value === '') $result['errors'][$field] = 'Name is required.';
            elseif (mb_strlen($value) > $limit) $result['errors'][$field] = 'Value is too long.';
            elseif ($field === 'imageUrl' && !$this->safeUrl($value)) $result['errors'][$field] = 'Image URL is unsafe.';
            else $result['data'][$this->snake($field)] = $value ?: null;
        }
        foreach (self::NUMBERS as $field) {
            if (!array_key_exists($field, $payload)) continue;
            if (!is_numeric($payload[$field])) $result['errors'][$field] = 'A number is required.';
            else $result['data'][$field] = (float) $payload[$field];
        }
        foreach (['width', 'height'] as $field) {
            if (isset($result['data'][$field]) && ($result['data'][$field] < 8 || $result['data'][$field] > 10000)) {
                $result['errors'][$field] = 'Size must be between 8 and 10000.';
            }
        }
        $this->optionalId($payload, 'characterId', $result);
        foreach (['hidden', 'locked'] as $field) {
            if (!array_key_exists($field, $payload)) continue;
            $value = filter_var($payload[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === null) $result['errors'][$field] = 'A boolean is required.';
            else $result['data'][$field] = $value ? 1 : 0;
        }
        if (array_key_exists('disposition', $payload)) {
            $value = strtolower(trim((string) $payload['disposition']));
            if (!in_array($value, self::DISPOSITIONS, true)) $result['errors']['disposition'] = 'Disposition is invalid.';
            else $result['data']['disposition'] = $value;
        }
        if (!$partial) $result['data'] += ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100];
        return $result;
    }

    private function optionalId(array $payload, string $field, array &$result): void
    {
        if (!array_key_exists($field, $payload)) return;
        if ($payload[$field] === null || $payload[$field] === '') {
            $result['data'][$this->snake($field)] = null;
            return;
        }
        $id = filter_var($payload[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) $result['errors'][$field] = 'A valid id is required.';
        else $result['data'][$this->snake($field)] = (int) $id;
    }

    private function result(array $result): array
    {
        return ['valid' => !$result['errors']] + $result;
    }

    private function unknownFields(array $payload, array $allowed, array &$result): void
    {
        foreach (array_diff(array_keys($payload), $allowed) as $field) {
            $result['errors'][$field] = 'Field is not writable.';
        }
    }

    private function safeUrl(string $value): bool
    {
        if ($value === '' || strpos($value, '/') === 0) return true;
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true)
            && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function snake(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}
