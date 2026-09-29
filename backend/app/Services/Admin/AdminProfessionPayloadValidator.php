<?php

namespace App\Services\Admin;

final class AdminProfessionPayloadValidator
{
    private const EDITABLE_FIELDS = [
        'name',
        'description',
        'details',
        'isAdvanced',
        'isMain',
        'skills',
        'talents',
    ];

    public function create(array $payload): array
    {
        $errors = $this->unknownFields($payload, array_merge(
            ['systemId'],
            self::EDITABLE_FIELDS
        ));
        $systemId = filter_var(
            $payload['systemId'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($systemId === false) {
            $errors['systemId'] = 'A valid RPG system is required.';
        }

        $data = ['system_id' => $systemId === false ? 0 : (int) $systemId];
        $definitions = [];
        $this->validateName($payload['name'] ?? null, $errors, $data);
        $this->validateText('description', $payload['description'] ?? null, $errors, $data);
        $this->validateText('details', $payload['details'] ?? null, $errors, $data);
        $this->validateBoolean('isAdvanced', $payload['isAdvanced'] ?? false, $errors, $data);
        $this->validateBoolean('isMain', $payload['isMain'] ?? true, $errors, $data);
        $this->validateDefinition('skills', $payload['skills'] ?? '', $errors, $definitions);
        $this->validateDefinition('talents', $payload['talents'] ?? '', $errors, $definitions);

        return [
            'valid' => !$errors,
            'errors' => $errors,
            'data' => $data,
            'definitions' => $definitions,
        ];
    }

    public function update(array $payload): array
    {
        $errors = $this->unknownFields(
            $payload,
            array_merge(['updatedAt'], self::EDITABLE_FIELDS)
        );
        $data = [];
        $definitions = [];
        foreach (self::EDITABLE_FIELDS as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            if ($field === 'name') {
                $this->validateName($payload[$field], $errors, $data);
            } elseif ($field === 'description' || $field === 'details') {
                $this->validateText($field, $payload[$field], $errors, $data);
            } elseif ($field === 'skills' || $field === 'talents') {
                $this->validateDefinition(
                    $field,
                    $payload[$field],
                    $errors,
                    $definitions
                );
            } else {
                $this->validateBoolean($field, $payload[$field], $errors, $data);
            }
        }
        if (!$data && !$definitions) {
            $errors['payload'] = 'At least one profession field must be provided.';
        }
        $updatedAt = $this->validateTimestamp($payload, $errors);

        return [
            'valid' => !$errors,
            'errors' => $errors,
            'data' => $data,
            'definitions' => $definitions,
            'updatedAt' => $updatedAt,
        ];
    }

    public function deletion(array $payload): array
    {
        $errors = $this->unknownFields($payload, ['updatedAt']);
        $updatedAt = $this->validateTimestamp($payload, $errors);
        return [
            'valid' => !$errors,
            'errors' => $errors,
            'updatedAt' => $updatedAt,
        ];
    }

    private function validateName($value, array &$errors, array &$data): void
    {
        $name = is_string($value) ? trim($value) : '';
        if ($name === '' || mb_strlen($name) > 255) {
            $errors['name'] = 'Name must contain between 1 and 255 characters.';
        }
        $data['name'] = $name;
    }

    private function validateText(
        string $field,
        $value,
        array &$errors,
        array &$data
    ): void {
        if ($value === null || $value === '') {
            $data[$field] = null;
            return;
        }
        if (!is_string($value) || strlen($value) > 65535) {
            $errors[$field] = 'Text must not exceed 65535 bytes.';
            return;
        }
        $data[$field] = trim($value) === '' ? null : trim($value);
    }

    private function validateBoolean(
        string $field,
        $value,
        array &$errors,
        array &$data
    ): void {
        if (!is_bool($value)) {
            $errors[$field] = 'Value must be boolean.';
            return;
        }
        $databaseField = $field === 'isAdvanced' ? 'is_advanced' : 'is_main';
        $data[$databaseField] = $value ? 1 : 0;
    }

    private function validateDefinition(
        string $field,
        $value,
        array &$errors,
        array &$definitions
    ): void {
        if ($value === null || $value === '') {
            $definitions[$field] = '';
            return;
        }
        if (!is_string($value) || strlen($value) > 65535) {
            $errors[$field] = 'Requirement expression must not exceed 65535 bytes.';
            return;
        }
        $definitions[$field] = trim($value);
    }

    private function validateTimestamp(array $payload, array &$errors)
    {
        if (!array_key_exists('updatedAt', $payload)) {
            $errors['updatedAt'] = 'The loaded update timestamp is required.';
            return null;
        }
        $value = $payload['updatedAt'];
        if ($value !== null && (!is_string($value) || strlen($value) > 64)) {
            $errors['updatedAt'] = 'The update timestamp is invalid.';
            return null;
        }
        return $value;
    }

    private function unknownFields(array $payload, array $allowed): array
    {
        $errors = [];
        foreach (array_diff(array_keys($payload), $allowed) as $field) {
            $errors[$field] = 'This field is not supported.';
        }
        return $errors;
    }
}
