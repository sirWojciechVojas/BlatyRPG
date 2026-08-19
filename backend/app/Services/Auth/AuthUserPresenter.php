<?php

namespace App\Services\Auth;

class AuthUserPresenter
{
    public function present(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'login' => (string) $user['username'],
            'email' => (string) $user['email'],
            'role' => UserRole::normalize($user['role'] ?? ''),
            'avatar_url' => $user['avatar_url'] ?? null,
        ];
    }
}
