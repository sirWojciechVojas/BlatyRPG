<?php

namespace App\Services\Admin;

use App\Services\Auth\PasswordPolicy;

class AdminPayloadValidator
{
    private const ROLES = ['player', 'gm', 'admin'];

    public function validateCreateUser(array $payload): array
    {
        $errors = $this->unknownFields($payload, ['username', 'email', 'password', 'role']);
        $username = is_string($payload['username'] ?? null)
            ? trim($payload['username']) : '';
        if (strlen($username) < 3 || strlen($username) > 100) {
            $errors['username'] = 'Username must contain between 3 and 100 characters.';
        }
        $email = is_string($payload['email'] ?? null)
            ? strtolower(trim($payload['email'])) : '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'A valid email address is required.';
        }
        $password = is_string($payload['password'] ?? null) ? $payload['password'] : '';
        if (!PasswordPolicy::isStrong($password)) {
            $errors['password'] = PasswordPolicy::MESSAGE;
        }
        $role = strtolower(trim((string) ($payload['role'] ?? 'player')));
        if (!in_array($role, self::ROLES, true)) {
            $errors['role'] = 'Unsupported user role.';
        }

        return [
            'valid' => !$errors,
            'errors' => $errors,
            'data' => compact('username', 'email', 'password', 'role'),
        ];
    }

    public function validateRole(array $payload): array
    {
        $errors = $this->unknownFields($payload, ['role']);
        $role = strtolower(trim((string) ($payload['role'] ?? '')));
        if (!in_array($role, self::ROLES, true)) {
            $errors['role'] = 'Unsupported user role.';
        }
        return ['valid' => !$errors, 'errors' => $errors, 'data' => ['role' => $role]];
    }

    private function unknownFields(array $payload, array $allowed): array
    {
        $errors = [];
        foreach (array_diff(array_keys($payload), $allowed) as $field) {
            $errors[$field] = 'This field is not supported.';
        }
        return $errors;
    }
}
