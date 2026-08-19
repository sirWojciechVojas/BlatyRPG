<?php

namespace App\Services\Campaign;

class CampaignPayloadValidator
{
    private const FIELDS = [
        'name',
        'description',
        'system_type',
        'systemType',
        'is_active',
        'isActive',
        'system_id',
        'systemId',
        'universe_id',
        'universeId',
    ];

    private $catalogPayload;

    public function __construct(?CampaignCatalogPayloadValidator $catalogPayload = null)
    {
        $this->catalogPayload = $catalogPayload ?: new CampaignCatalogPayloadValidator();
    }

    public function validateCreate(array $payload): array
    {
        $errors = [];
        $data = [];
        foreach (array_diff(array_keys($payload), self::FIELDS) as $field) {
            $errors[$field] = 'This field is not supported.';
        }

        $name = $payload['name'] ?? null;
        if (!is_string($name) || trim($name) === '') {
            $errors['name'] = 'A campaign name is required.';
        } elseif (strlen(trim($name)) > 255) {
            $errors['name'] = 'The campaign name cannot exceed 255 characters.';
        } else {
            $data['name'] = trim($name);
        }

        if (array_key_exists('description', $payload)) {
            $description = $payload['description'];
            if ($description !== null && !is_string($description)) {
                $errors['description'] = 'The description must be a string or null.';
            } elseif (is_string($description) && strlen(trim($description)) > 10000) {
                $errors['description'] = 'The description cannot exceed 10000 characters.';
            } else {
                $data['description'] = $description === null || trim($description) === ''
                    ? null : trim($description);
            }
        }

        $this->rejectConflictingAliases($payload, 'system_type', 'systemType', $errors);
        $systemErrorKey = array_key_exists('systemType', $payload) ? 'systemType' : 'system_type';
        $system = strtolower(trim((string) (
            $payload['systemType'] ?? $payload['system_type'] ?? 'wfrp2ed'
        )));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,49}$/', $system)) {
            $errors[$systemErrorKey] = 'The system type must be a safe identifier up to 50 characters.';
        } else {
            $data['system_type'] = $system;
        }

        $this->rejectConflictingAliases($payload, 'is_active', 'isActive', $errors);
        $activeErrorKey = array_key_exists('isActive', $payload) ? 'isActive' : 'is_active';
        if (array_key_exists('is_active', $payload) || array_key_exists('isActive', $payload)) {
            $value = $payload['isActive'] ?? $payload['is_active'];
            $active = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active === null) {
                $errors[$activeErrorKey] = 'The active flag must be boolean.';
            } else {
                $data['is_active'] = $active ? 1 : 0;
            }
        } else {
            $data['is_active'] = 1;
        }

        $catalog = $this->catalogPayload->validate($payload);
        $data += $catalog['data'];
        $errors += $catalog['errors'];

        return ['valid' => !$errors, 'errors' => $errors, 'data' => $data];
    }

    private function rejectConflictingAliases(
        array $payload,
        string $legacy,
        string $canonical,
        array &$errors
    ): void {
        if (!array_key_exists($legacy, $payload) || !array_key_exists($canonical, $payload)) {
            return;
        }
        if ($payload[$legacy] !== $payload[$canonical]) {
            $errors[$canonical] = 'Conflicting aliases are not allowed.';
        }
    }
}
