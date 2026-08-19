<?php

namespace App\Services\Auth;

final class AuthPayloadValidator
{
    private const USERNAME_MIN = 3;
    private const USERNAME_MAX = 100;
    private const EMAIL_MAX = 255;
    private const PASSWORD_MIN = 12;
    private const PASSWORD_MAX = 200;

    public function register(array $payload): array
    {
        $allowed = ['username', 'email', 'password', 'confirm_password', 'confirmPassword'];
        $errors = $this->unknown($payload, $allowed);
        $username = $this->username($payload['username'] ?? null, $errors);
        $email = $this->email($payload['email'] ?? null, $errors);
        $password = $payload['password'] ?? null;
        $this->strongPassword($password, 'password', $errors);
        $confirmation = $this->alias($payload, 'confirmPassword', 'confirm_password', $errors);
        if (!$confirmation['present'] || !is_string($confirmation['value'])
            || !is_string($password) || !hash_equals($password, $confirmation['value'])) {
            $errors['confirmPassword'] = 'Password confirmation does not match.';
        }

        return $this->result($errors, [
            'username' => $username,
            'email' => $email,
            'password' => is_string($password) ? $password : '',
        ]);
    }

    public function login(array $payload): array
    {
        $errors = $this->unknown($payload, ['email', 'login', 'username', 'password']);
        $identifier = '';
        $identifiers = [];
        foreach (['email', 'login', 'username'] as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            if (!is_string($payload[$field])) {
                $errors['credentials'] = 'Invalid credentials.';
                continue;
            }
            $candidate = trim($payload[$field]);
            if ($candidate !== '') {
                $identifiers[] = $candidate;
            }
        }
        if ($identifiers) {
            $identifier = $identifiers[0];
        }
        if (count(array_unique($identifiers)) > 1) {
            $errors['credentials'] = 'Invalid credentials.';
        }
        $password = $payload['password'] ?? null;
        if ($identifier === '' || strlen($identifier) > self::EMAIL_MAX
            || preg_match('/[\x00-\x1F\x7F]/', $identifier)) {
            $errors['credentials'] = 'Invalid credentials.';
        }
        if (!is_string($password) || $password === '' || strlen($password) > self::PASSWORD_MAX) {
            $errors['credentials'] = 'Invalid credentials.';
        }

        return $this->result($errors, [
            'identifier' => $identifier,
            'password' => is_string($password) ? $password : '',
        ]);
    }

    public function profile(array $payload): array
    {
        $allowed = ['username', 'email', 'avatarUrl', 'avatar_url'];
        $errors = $this->unknown($payload, $allowed);
        $data = [];
        if (array_key_exists('username', $payload)) {
            $data['username'] = $this->username($payload['username'], $errors);
        }
        if (array_key_exists('email', $payload)) {
            $data['email'] = $this->email($payload['email'], $errors);
        }
        $avatar = $this->alias($payload, 'avatarUrl', 'avatar_url', $errors);
        if ($avatar['present']) {
            if ($avatar['value'] === null || $avatar['value'] === '') {
                $data['avatar_url'] = null;
            } elseif (!is_string($avatar['value']) || !$this->validAvatar($avatar['value'])) {
                $errors['avatarUrl'] = 'Avatar must be a safe HTTPS URL or local asset path.';
            } else {
                $data['avatar_url'] = trim($avatar['value']);
            }
        }
        if (!$data && !$errors) {
            $errors['payload'] = 'At least one profile field is required.';
        }
        return $this->result($errors, $data);
    }

    public function changePassword(array $payload): array
    {
        $allowed = [
            'currentPassword', 'current_password', 'newPassword', 'new_password',
            'confirmPassword', 'confirm_password',
        ];
        $errors = $this->unknown($payload, $allowed);
        $current = $this->alias($payload, 'currentPassword', 'current_password', $errors);
        $new = $this->alias($payload, 'newPassword', 'new_password', $errors);
        $confirm = $this->alias($payload, 'confirmPassword', 'confirm_password', $errors);
        if (!$current['present'] || !is_string($current['value']) || $current['value'] === ''
            || strlen($current['value']) > self::PASSWORD_MAX) {
            $errors['currentPassword'] = 'Current password is invalid.';
        }
        $this->strongPassword($new['value'], 'newPassword', $errors);
        if (!$confirm['present'] || !is_string($confirm['value']) || !is_string($new['value'])
            || !hash_equals($new['value'], $confirm['value'])) {
            $errors['confirmPassword'] = 'Password confirmation does not match.';
        }
        if (is_string($current['value']) && is_string($new['value'])
            && $current['value'] !== '' && hash_equals($current['value'], $new['value'])) {
            $errors['newPassword'] = 'New password must differ from the current password.';
        }
        return $this->result($errors, [
            'current_password' => is_string($current['value']) ? $current['value'] : '',
            'new_password' => is_string($new['value']) ? $new['value'] : '',
        ]);
    }

    public function resetRequest(array $payload): array
    {
        $errors = $this->unknown($payload, ['email']);
        $email = $this->email($payload['email'] ?? null, $errors);
        return $this->result($errors, ['email' => $email]);
    }

    public function resetConfirm(array $payload): array
    {
        $allowed = ['token', 'password', 'confirmPassword', 'confirm_password'];
        $errors = $this->unknown($payload, $allowed);
        $tokenValue = $payload['token'] ?? null;
        $token = is_string($tokenValue) ? strtolower(trim($tokenValue)) : '';
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            $errors['token'] = 'Password reset token is invalid or expired.';
        }
        $password = $payload['password'] ?? null;
        $this->strongPassword($password, 'password', $errors);
        $confirm = $this->alias($payload, 'confirmPassword', 'confirm_password', $errors);
        if (!$confirm['present'] || !is_string($confirm['value']) || !is_string($password)
            || !hash_equals($password, $confirm['value'])) {
            $errors['confirmPassword'] = 'Password confirmation does not match.';
        }
        return $this->result($errors, [
            'token' => $token,
            'password' => is_string($password) ? $password : '',
        ]);
    }

    private function username($value, array &$errors): string
    {
        $username = is_string($value) ? trim($value) : '';
        $length = mb_strlen($username);
        if ($length < self::USERNAME_MIN || $length > self::USERNAME_MAX
            || preg_match('/[\x00-\x1F\x7F]/', $username)) {
            $errors['username'] = 'Username must contain between 3 and 100 safe characters.';
        }
        return $username;
    }

    private function email($value, array &$errors): string
    {
        $email = is_string($value) ? strtolower(trim($value)) : '';
        if (strlen($email) > self::EMAIL_MAX || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        }
        return $email;
    }

    private function strongPassword($value, string $key, array &$errors): void
    {
        if (!PasswordPolicy::isStrong($value)) {
            $errors[$key] = PasswordPolicy::MESSAGE;
        }
    }

    private function validAvatar(string $value): bool
    {
        $value = trim($value);
        if (strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F\\\\]/', $value)) {
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

    private function alias(array $payload, string $canonical, string $legacy, array &$errors): array
    {
        $hasCanonical = array_key_exists($canonical, $payload);
        $hasLegacy = array_key_exists($legacy, $payload);
        if ($hasCanonical && $hasLegacy && $payload[$canonical] !== $payload[$legacy]) {
            $errors[$canonical] = 'Conflicting aliases are not allowed.';
        }
        return [
            'present' => $hasCanonical || $hasLegacy,
            'value' => $hasCanonical ? $payload[$canonical] : ($payload[$legacy] ?? null),
        ];
    }

    private function unknown(array $payload, array $allowed): array
    {
        $errors = [];
        foreach (array_diff(array_keys($payload), $allowed) as $field) {
            $errors[$field] = 'This field is not supported.';
        }
        return $errors;
    }

    private function result(array $errors, array $data): array
    {
        return ['valid' => !$errors, 'errors' => $errors, 'data' => $data];
    }
}
