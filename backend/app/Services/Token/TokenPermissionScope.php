<?php

namespace App\Services\Token;

final class TokenPermissionScope
{
    public const GM = 'gm';
    public const USERS = 'users';
    public const EVERYONE = 'everyone';
    public const INHERIT = 'inherit';

    private const MODES = [self::GM, self::USERS, self::EVERYONE, self::INHERIT];

    public static function validate($value): array
    {
        if (!is_array($value) || array_values($value) === $value) {
            return self::invalid('Use an object with mode and userIds.');
        }
        if (array_diff(array_keys($value), ['mode', 'userIds'])) {
            return self::invalid('Permission scope contains an unknown field.');
        }
        $mode = strtolower(trim((string) ($value['mode'] ?? '')));
        if (!in_array($mode, self::MODES, true)) {
            return self::invalid('Use gm, users, everyone or inherit.');
        }
        $rawIds = $value['userIds'] ?? [];
        if (!is_array($rawIds) || count($rawIds) > 200) {
            return self::invalid('userIds must contain at most 200 users.');
        }
        $userIds = [];
        foreach ($rawIds as $valueId) {
            $id = filter_var($valueId, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($id === false) return self::invalid('Every user id must be valid.');
            $userIds[(int) $id] = (int) $id;
        }
        $userIds = array_values($userIds);
        sort($userIds, SORT_NUMERIC);
        if ($mode === self::USERS && !$userIds) {
            return self::invalid('Select at least one user.');
        }
        if ($mode !== self::USERS && $userIds) {
            return self::invalid('userIds are only accepted in users mode.');
        }
        return ['valid' => true, 'data' => compact('mode', 'userIds'), 'error' => null];
    }

    public static function stored($value, string $fallback): array
    {
        if (is_string($value)) $value = json_decode($value, true);
        $result = self::validate($value);
        return $result['valid']
            ? $result['data']
            : ['mode' => $fallback, 'userIds' => []];
    }

    public static function allows(array $scope, int $userId, bool $inherited): bool
    {
        if ($userId < 1) return false;
        if ($scope['mode'] === self::EVERYONE) return true;
        if ($scope['mode'] === self::USERS) {
            return in_array($userId, $scope['userIds'], true);
        }
        return $scope['mode'] === self::INHERIT ? $inherited : false;
    }

    public static function userIds(array $permissionData): array
    {
        $ids = [];
        $fields = [
            'visible_to_json', 'controlled_by_json',
            'editable_by_json', 'observer_by_json',
        ];
        foreach (array_intersect_key($permissionData, array_flip($fields)) as $scope) {
            foreach ((array) ($scope['userIds'] ?? []) as $id) $ids[(int) $id] = (int) $id;
        }
        return array_values(array_filter($ids));
    }

    private static function invalid(string $message): array
    {
        return ['valid' => false, 'data' => null, 'error' => $message];
    }
}
