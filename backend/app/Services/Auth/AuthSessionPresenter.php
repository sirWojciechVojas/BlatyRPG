<?php

namespace App\Services\Auth;

final class AuthSessionPresenter
{
    public function present(array $session, int $currentSessionId): array
    {
        return [
            'id' => (int) ($session['id'] ?? 0),
            'isCurrent' => (int) ($session['id'] ?? 0) === $currentSessionId,
            'createdAt' => $session['created_at'] ?? null,
            'lastSeenAt' => $session['last_seen_at'] ?? null,
            'expiresAt' => $session['expires_at'] ?? null,
        ];
    }
}
