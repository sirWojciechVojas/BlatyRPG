<?php

namespace App\Services\Auth;

final class PasswordPolicy
{
    public const MESSAGE =
        'Password must be 12-200 characters and include upper, lower and numeric characters.';

    public static function isStrong($password): bool
    {
        return is_string($password)
            && strlen($password) >= 12
            && strlen($password) <= 200
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1;
    }
}
