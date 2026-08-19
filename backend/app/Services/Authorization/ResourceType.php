<?php

namespace App\Services\Authorization;

final class ResourceType
{
    public const CAMPAIGN = 'campaign';
    public const CHARACTER = 'character';
    public const SCENE = 'scene';
    public const JOURNAL = 'journal';
    public const ITEM = 'item';
    public const SHARED = 'resource';

    public static function normalize($type): ?string
    {
        $value = strtolower(trim((string) $type));
        return in_array($value, self::values(), true) ? $value : null;
    }

    public static function values(): array
    {
        return [
            self::CAMPAIGN, self::CHARACTER, self::SCENE,
            self::JOURNAL, self::ITEM, self::SHARED,
        ];
    }

    public static function table(string $type): ?string
    {
        $map = [
            self::CAMPAIGN => 'campaigns',
            self::CHARACTER => 'characters',
            self::SCENE => 'scenes',
            self::JOURNAL => 'journals',
            self::ITEM => 'shop_item_instances',
        ];
        return $map[$type] ?? null;
    }
}
