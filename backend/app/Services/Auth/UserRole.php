<?php

namespace App\Services\Auth;

final class UserRole
{
    public const PLAYER = 'player';
    public const GM = 'gm';
    public const ADMIN = 'admin';

    public static function normalize($role): string
    {
        $role = strtolower(trim((string) $role));
        return $role === 'user' ? self::PLAYER : $role;
    }

    public static function isSupported($role): bool
    {
        return in_array(self::normalize($role), self::all(), true);
    }

    public static function all(): array
    {
        return [self::PLAYER, self::GM, self::ADMIN];
    }
}
