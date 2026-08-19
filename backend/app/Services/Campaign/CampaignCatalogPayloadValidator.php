<?php

namespace App\Services\Campaign;

final class CampaignCatalogPayloadValidator
{
    public const FIELDS = [
        'systemId',
        'system_id',
        'universeId',
        'universe_id',
    ];

    public function validate(array $payload): array
    {
        $errors = [];
        $system = $this->aliasedValue($payload, 'systemId', 'system_id', $errors);
        $universe = $this->aliasedValue(
            $payload,
            'universeId',
            'universe_id',
            $errors
        );
        $hasSystem = $system !== null;
        $hasUniverse = $universe !== null;

        if (!$hasSystem && !$hasUniverse && !$errors) {
            return ['valid' => true, 'data' => [], 'errors' => []];
        }
        if (!$hasSystem) {
            $errors['systemId'] = 'An RPG system is required when a world is selected.';
        }
        if (!$hasUniverse) {
            $errors['universeId'] = 'A world is required when an RPG system is selected.';
        }

        $systemId = $this->positiveId($system);
        $universeId = $this->positiveId($universe);
        if ($hasSystem && !$systemId) {
            $errors['systemId'] = 'The RPG system ID must be a positive integer.';
        }
        if ($hasUniverse && !$universeId) {
            $errors['universeId'] = 'The world ID must be a positive integer.';
        }

        return [
            'valid' => !$errors,
            'data' => !$errors ? [
                'rpg_system_id' => $systemId,
                'rpg_universe_id' => $universeId,
            ] : [],
            'errors' => $errors,
        ];
    }

    private function aliasedValue(
        array $payload,
        string $canonical,
        string $legacy,
        array &$errors
    ) {
        $hasCanonical = array_key_exists($canonical, $payload);
        $hasLegacy = array_key_exists($legacy, $payload);
        if ($hasCanonical && $hasLegacy
            && (string) $payload[$canonical] !== (string) $payload[$legacy]) {
            $errors[$canonical] = 'Conflicting aliases are not allowed.';
        }
        if ($hasCanonical) {
            return $payload[$canonical];
        }
        return $hasLegacy ? $payload[$legacy] : null;
    }

    private function positiveId($value): ?int
    {
        if (!is_int($value)
            && !(is_string($value) && preg_match('/^[1-9][0-9]*$/', $value))) {
            return null;
        }
        $id = (int) $value;
        return $id > 0 && $id <= 4294967295 ? $id : null;
    }
}
