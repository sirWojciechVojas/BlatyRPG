<?php

namespace App\Services\Campaign;

final class CampaignSettingsValidator
{
    private const STATUSES = ['active', 'paused', 'archived'];
    private const MAX_SETTINGS_BYTES = 65536;

    public function validate(array $payload): array
    {
        $allowed = ['name', 'description', 'bannerUrl', 'banner_url', 'status', 'settings'];
        $unknown = array_diff(array_keys($payload), $allowed);
        $errors = $unknown
            ? ['payload' => 'Unsupported fields: ' . implode(', ', $unknown) . '.']
            : [];
        $data = [];
        $this->stringField($payload, 'name', 1, 255, false, $data, $errors);
        $this->stringField($payload, 'description', 0, 10000, true, $data, $errors);
        $this->banner($payload, $data, $errors);
        if (array_key_exists('status', $payload)) {
            $status = strtolower(trim((string) $payload['status']));
            if (!in_array($status, self::STATUSES, true)) {
                $errors['status'] = 'Status must be active, paused or archived.';
            } else {
                $data['status'] = $status;
                $data['is_active'] = $status === 'active' ? 1 : 0;
            }
        }
        if (array_key_exists('settings', $payload)) {
            if (!is_array($payload['settings'])) {
                $errors['settings'] = 'Settings must be a JSON object.';
            } elseif (strlen((string) json_encode($payload['settings'])) > self::MAX_SETTINGS_BYTES) {
                $errors['settings'] = 'Settings exceed the 64 KiB limit.';
            } else {
                $data['settings_json'] = $payload['settings'];
            }
        }
        if (!$data && !$errors) {
            $errors['payload'] = 'At least one editable field is required.';
        }
        return ['valid' => !$errors, 'data' => $data, 'errors' => $errors];
    }

    private function banner(array $payload, array &$data, array &$errors): void
    {
        $hasCamel = array_key_exists('bannerUrl', $payload);
        $hasSnake = array_key_exists('banner_url', $payload);
        if (!$hasCamel && !$hasSnake) {
            return;
        }
        if ($hasCamel && $hasSnake && $payload['bannerUrl'] !== $payload['banner_url']) {
            $errors['bannerUrl'] = 'Conflicting aliases are not allowed.';
            return;
        }
        $value = trim((string) ($hasCamel ? $payload['bannerUrl'] : $payload['banner_url']));
        if (strlen($value) > 2048 || ($value !== '' && !$this->assetReference($value))) {
            $errors['bannerUrl'] = 'Banner must be an HTTPS URL or safe local asset path.';
            return;
        }
        $data['banner_url'] = $value === '' ? null : $value;
    }

    private function stringField(
        array $payload,
        string $field,
        int $min,
        int $max,
        bool $nullable,
        array &$data,
        array &$errors
    ): void {
        if (!array_key_exists($field, $payload)) {
            return;
        }
        if (!is_string($payload[$field]) && !($nullable && $payload[$field] === null)) {
            $errors[$field] = 'This field must be a string.';
            return;
        }
        $value = trim((string) $payload[$field]);
        $length = mb_strlen($value);
        if ($length < $min || $length > $max) {
            $errors[$field] = "This field must contain between {$min} and {$max} characters.";
            return;
        }
        $data[$field] = $nullable && $value === '' ? null : $value;
    }

    private function assetReference(string $value): bool
    {
        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $value)) {
            return false;
        }
        if (strpos($value, 'https://') === 0) {
            return filter_var($value, FILTER_VALIDATE_URL) !== false;
        }
        return (bool) preg_match(
            '#^/(?!/)(?!.*(?:/\.\.?)(?:/|$))[A-Za-z0-9_.~/-]+$#',
            $value
        );
    }
}
