<?php

namespace App\Services\Audio;

use App\Services\Auth\AuthException;

final class UserAudioDeviceSettingsValidator
{
    private const MAX_DEVICE_ID_LENGTH = 512;
    private const EXTERNAL_INPUT_KEYS = ['external-1', 'external-2'];

    public function validate(array $payload): array
    {
        $errors = [];
        $allowed = ['microphoneDeviceId', 'outputDeviceId', 'externalInputs'];
        foreach (array_diff(array_keys($payload), $allowed) as $field) {
            $errors[$field] = 'This field is not supported.';
        }

        $external = $payload['externalInputs'] ?? [];
        if (!is_array($external)) {
            $errors['externalInputs'] = 'External inputs must be an object.';
            $external = [];
        }
        foreach (array_diff(array_keys($external), self::EXTERNAL_INPUT_KEYS) as $field) {
            $errors['externalInputs.' . $field] = 'This external input is not supported.';
        }

        $result = [
            'microphoneDeviceId' => $this->deviceId(
                $payload['microphoneDeviceId'] ?? null,
                'microphoneDeviceId',
                $errors
            ),
            'outputDeviceId' => $this->deviceId(
                $payload['outputDeviceId'] ?? null,
                'outputDeviceId',
                $errors
            ),
            'externalInputs' => [],
        ];
        foreach (self::EXTERNAL_INPUT_KEYS as $key) {
            $result['externalInputs'][$key] = $this->deviceId(
                $external[$key] ?? null,
                'externalInputs.' . $key,
                $errors
            );
        }

        if ($errors) {
            throw new AuthException(
                'audio_device_settings_invalid',
                'Audio device settings are invalid.',
                422,
                $errors
            );
        }
        return $result;
    }

    private function deviceId($value, string $field, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)
            || strlen($value) > self::MAX_DEVICE_ID_LENGTH
            || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            $errors[$field] = 'Device identifier is invalid.';
            return null;
        }
        return $value;
    }
}
