<?php

namespace App\Services\Character;

final class CharacterPayloadValidator
{
    private const MAX_JSON_BYTES = 262144;
    private const MAX_VALUES = 5000;
    private const MAX_DEPTH = 12;

    public function validateUpdate(array $payload): array
    {
        $allowed = [
            'name', 'data', 'avatarUrl', 'avatar_url', 'updatedAt', 'updated_at',
            'revision', 'expectedRevision', 'expected_revision',
        ];
        $errors = $this->unknownFields($payload, $allowed);
        $data = [];

        if (array_key_exists('name', $payload)) {
            if (!is_string($payload['name'])) {
                $errors['name'] = 'Name must be a string.';
            } else {
                $name = trim($payload['name']);
                if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
                    $errors['name'] = 'Name must contain between 2 and 150 characters.';
                } else {
                    $data['name'] = $name;
                }
            }
        }
        if (array_key_exists('data', $payload)) {
            if (!is_array($payload['data'])) {
                $errors['data'] = 'Character data must be a JSON object.';
            } else {
                $dataError = $this->validateJsonData($payload['data']);
                if ($dataError) {
                    $errors['data'] = $dataError;
                } else {
                    $data['data'] = $payload['data'];
                }
            }
        }
        $avatar = $this->aliased($payload, 'avatarUrl', 'avatar_url', $errors);
        if ($avatar['present']) {
            if ($avatar['value'] !== null && !is_string($avatar['value'])) {
                $errors['avatarUrl'] = 'Avatar must be a string or null.';
            } else {
                $value = trim((string) $avatar['value']);
                if (!$this->validAssetReference($value)) {
                    $errors['avatarUrl'] = 'Avatar must be an HTTPS URL, local path, or safe asset identifier.';
                } else {
                    $data['avatar_url'] = $value ?: null;
                }
            }
        }
        $version = $this->aliased($payload, 'updatedAt', 'updated_at', $errors);
        $expectedUpdatedAt = null;
        if ($version['present']) {
            if ($version['value'] !== null && !is_string($version['value'])) {
                $errors['updatedAt'] = 'The character version must be a string or null.';
            } elseif ($version['value'] !== null && $version['value'] !== '') {
                $expectedUpdatedAt = trim($version['value']);
                if (strlen($expectedUpdatedAt) > 40 || strtotime($expectedUpdatedAt) === false) {
                    $errors['updatedAt'] = 'The character version is invalid.';
                }
            }
        }
        $expectedRevision = $this->expectedRevision($payload, $errors);
        if (!$data && !$errors) {
            $errors['payload'] = 'At least one editable field is required.';
        }

        return [
            'valid' => !$errors,
            'data' => $data,
            'expectedRevision' => $expectedRevision,
            'expectedUpdatedAt' => $expectedUpdatedAt,
            'errors' => $errors,
        ];
    }

    public function validateCreate(array $payload): array
    {
        $allowed = [
            'campaignId', 'campaign_id', 'systemId', 'system_id',
            'universeId', 'universe_id', 'name', 'data', 'avatarUrl', 'avatar_url',
            'assetSetId', 'asset_set_id',
        ];
        $errors = $this->unknownFields($payload, $allowed);
        $ids = [];
        foreach ([
            'campaign' => ['campaignId', 'campaign_id'],
            'system' => ['systemId', 'system_id'],
            'universe' => ['universeId', 'universe_id'],
        ] as $label => $aliases) {
            $resolved = $this->aliased($payload, $aliases[0], $aliases[1], $errors);
            $value = filter_var($resolved['value'], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if (!$resolved['present'] || $value === false) {
                $errors[$aliases[0]] = ucfirst($label) . ' id must be a positive integer.';
            } else {
                $ids[$label . '_id'] = (int) $value;
            }
        }

        $mutable = array_intersect_key($payload, array_flip([
            'name', 'data', 'avatarUrl', 'avatar_url',
        ]));
        if (!array_key_exists('name', $mutable)) {
            $errors['name'] = 'Name is required.';
        }
        $validated = $this->validateUpdate($mutable);
        $errors = array_merge($errors, $validated['errors']);
        $assetSetId = $this->optionalAssetSetId($payload, $errors);
        return [
            'valid' => !$errors,
            'data' => $ids + $validated['data'],
            'assetSetId' => $assetSetId,
            'errors' => $errors,
        ];
    }

    private function expectedRevision(array $payload, array &$errors): ?int
    {
        $values = [];
        foreach (['revision', 'expectedRevision', 'expected_revision'] as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            $value = filter_var($payload[$key], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($value === false) {
                $errors['revision'] = 'Revision must be a positive integer.';
                continue;
            }
            $values[] = (int) $value;
        }
        if (count(array_unique($values)) > 1) {
            $errors['revision'] = 'Conflicting revision aliases were provided.';
        }
        return $values ? $values[0] : null;
    }

    private function optionalAssetSetId(array $payload, array &$errors): ?int
    {
        $values = [];
        foreach (['assetSetId', 'asset_set_id'] as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            $raw = $payload[$key];
            if ($raw === null || $raw === '' || $raw === 0 || $raw === '0') {
                $values[] = null;
                continue;
            }
            $value = filter_var($raw, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($value === false) {
                $errors['assetSetId'] = 'Asset set id must be a positive integer.';
                continue;
            }
            $values[] = (int) $value;
        }
        if (count(array_unique($values, SORT_REGULAR)) > 1) {
            $errors['assetSetId'] = 'Conflicting asset set aliases were provided.';
        }
        return $values ? $values[0] : null;
    }

    private function validateJsonData(array $data): ?string
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false || strlen($encoded) > self::MAX_JSON_BYTES) {
            return 'Character data exceeds the 256 KiB limit.';
        }
        $count = 0;
        return $this->inspectValue($data, 0, $count);
    }

    private function inspectValue($value, int $depth, int &$count): ?string
    {
        if (++$count > self::MAX_VALUES) {
            return 'Character data contains too many values.';
        }
        if ($depth > self::MAX_DEPTH) {
            return 'Character data is nested too deeply.';
        }
        if (is_string($value) && mb_strlen($value) > 20000) {
            return 'A character data value is too long.';
        }
        if (is_float($value) && !is_finite($value)) {
            return 'Character data contains an invalid number.';
        }
        if (!is_array($value)) {
            return null;
        }
        foreach ($value as $key => $child) {
            $key = (string) $key;
            if (strlen($key) > 100 || preg_match('/[\x00-\x1F]/', $key)
                || in_array(strtolower($key), ['__proto__', 'prototype', 'constructor'], true)) {
                return 'Character data contains an unsafe key.';
            }
            $error = $this->inspectValue($child, $depth + 1, $count);
            if ($error) {
                return $error;
            }
        }
        return null;
    }

    private function unknownFields(array $payload, array $allowed): array
    {
        $unknown = array_values(array_diff(array_keys($payload), $allowed));
        return $unknown ? ['payload' => 'Unsupported fields: ' . implode(', ', $unknown) . '.'] : [];
    }

    private function aliased(array $payload, string $canonical, string $legacy, array &$errors): array
    {
        $hasCanonical = array_key_exists($canonical, $payload);
        $hasLegacy = array_key_exists($legacy, $payload);
        if ($hasCanonical && $hasLegacy && $payload[$canonical] !== $payload[$legacy]) {
            $errors[$canonical] = 'Conflicting aliases were provided.';
        }
        return [
            'present' => $hasCanonical || $hasLegacy,
            'value' => $hasCanonical ? $payload[$canonical] : ($payload[$legacy] ?? null),
        ];
    }

    private function validAssetReference(string $value): bool
    {
        if ($value === '') {
            return true;
        }
        if (strlen($value) > 255 || preg_match('/[\x00-\x1F]/', $value)
            || strpos($value, '\\') !== false) {
            return false;
        }
        return (bool) preg_match('#^(?:https://[^\s]+|/[^\s]+|[A-Za-z0-9][A-Za-z0-9_./-]*)$#', $value);
    }
}
