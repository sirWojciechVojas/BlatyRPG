<?php

namespace App\Services\Combat;

final class CombatCommandValidator
{
    private const FIELDS = [
        'start' => ['action', 'tokenIds', 'revision'],
        'end' => ['action', 'revision'],
        'next' => ['action', 'revision'],
        'previous' => ['action', 'revision'],
        'toggle' => ['action', 'revision', 'tokenId'],
        'initiative' => ['action', 'revision', 'tokenId', 'initiative'],
        'resetMovement' => ['action', 'tokenIds'],
        'setMovement' => [
            'action', 'tokenId', 'tokenRevision', 'movementRange',
            'movementPoints', 'movementResetMode',
        ],
    ];

    public static function assertFields(string $action, array $payload): void
    {
        if (!isset(self::FIELDS[$action])) return;
        $unexpected = array_diff(array_keys($payload), self::FIELDS[$action]);
        if (!$unexpected) return;
        throw new CombatException(
            'validation_failed', 'Combat command contains unexpected fields.', 422,
            array_fill_keys($unexpected, 'This field is not accepted.')
        );
    }
}
