<?php

namespace App\Services\Auth;

final class AuthSessionPolicy
{
    public function resolve(array $decoded, ?array $session, ?array $user, ?int $now = null): array
    {
        $timestamp = $now ?? time();
        if (!$session || !$user) {
            return ['valid' => false];
        }

        $sessionExpiresAt = strtotime((string) ($session['expires_at'] ?? ''));
        $role = UserRole::normalize($user['role'] ?? '');
        $valid = empty($session['revoked_at'])
            && empty($user['deleted_at'])
            && (int) ($session['id'] ?? 0) > 0
            && (int) ($session['user_id'] ?? 0) === (int) ($decoded['user_id'] ?? 0)
            && (int) ($user['id'] ?? 0) === (int) ($session['user_id'] ?? 0)
            && $sessionExpiresAt !== false
            && $sessionExpiresAt > $timestamp
            && (int) ($decoded['expires_at'] ?? 0) > $timestamp
            && UserRole::isSupported($role);

        if (!$valid) {
            return ['valid' => false];
        }

        return [
            'valid' => true,
            'user_id' => (int) $user['id'],
            'role' => $role,
            'session_id' => (int) $session['id'],
            'expires_at' => (int) $decoded['expires_at'],
        ];
    }
}
