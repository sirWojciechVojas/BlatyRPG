<?php

namespace App\Services\Auth;

final class UserRole
{
    public const USER = 'user';
    public const ADMIN = 'admin';

    public static function normalize($role): string
    {
        $role = strtolower(trim((string) $role));
        return in_array($role, ['player', 'gm'], true) ? self::USER : $role;
    }

    public static function isSupported($role): bool
    {
        return in_array(self::normalize($role), self::all(), true);
    }

    public static function all(): array
    {
        return [self::USER, self::ADMIN];
    }
}
