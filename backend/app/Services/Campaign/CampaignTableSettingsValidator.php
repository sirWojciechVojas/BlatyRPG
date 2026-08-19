<?php

namespace App\Services\Campaign;

final class CampaignTableSettingsValidator
{
    private const BOOLEAN_FIELDS = [
        'allowPlayerDrawing',
        'allowPlayerTokenMovement',
        'autoOpenLastScene',
        'showPlayerCursors',
    ];

    public function validate(array $settings): array
    {
        $allowed = array_merge(self::BOOLEAN_FIELDS, [
            'tableVisibility', 'diceVisibility', 'defaultGridSize',
        ]);
        $unknown = array_diff(array_keys($settings), $allowed);
        $errors = $unknown
            ? ['settings' => 'Unsupported table settings: ' . implode(', ', $unknown) . '.']
            : [];
        $data = [];

        foreach (self::BOOLEAN_FIELDS as $field) {
            if (!array_key_exists($field, $settings)) {
                continue;
            }
            if (!is_bool($settings[$field])) {
                $errors["settings.{$field}"] = 'This setting must be boolean.';
            } else {
                $data[$field] = $settings[$field];
            }
        }
        $this->choice(
            $settings,
            'tableVisibility',
            ['private', 'invite_only'],
            $data,
            $errors
        );
        $this->choice(
            $settings,
            'diceVisibility',
            ['public', 'gm', 'private'],
            $data,
            $errors
        );
        if (array_key_exists('defaultGridSize', $settings)) {
            $value = filter_var(
                $settings['defaultGridSize'],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 16, 'max_range' => 256]]
            );
            if ($value === false) {
                $errors['settings.defaultGridSize'] = 'Grid size must be between 16 and 256.';
            } else {
                $data['defaultGridSize'] = (int) $value;
            }
        }

        return ['valid' => !$errors, 'data' => $data, 'errors' => $errors];
    }

    private function choice(
        array $settings,
        string $field,
        array $allowed,
        array &$data,
        array &$errors
    ): void {
        if (!array_key_exists($field, $settings)) {
            return;
        }
        $value = strtolower(trim((string) $settings[$field]));
        if (!in_array($value, $allowed, true)) {
            $errors["settings.{$field}"] = 'This setting contains an unsupported value.';
        } else {
            $data[$field] = $value;
        }
    }
}
