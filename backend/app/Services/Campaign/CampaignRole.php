<?php

namespace App\Services\Campaign;

final class CampaignRole
{
    public const GM = 'gm';
    public const ASSISTANT = 'assistant';
    public const PLAYER = 'player';
    public const OBSERVER = 'observer';

    public static function values(): array
    {
        return [self::GM, self::ASSISTANT, self::PLAYER, self::OBSERVER];
    }

    public static function normalize($role): ?string
    {
        $value = strtolower(trim((string) $role));
        return in_array($value, self::values(), true) ? $value : null;
    }
}
