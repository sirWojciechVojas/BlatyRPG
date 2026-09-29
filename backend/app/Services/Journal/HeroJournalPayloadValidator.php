<?php

namespace App\Services\Journal;

final class HeroJournalPayloadValidator
{
    public const TYPES = ['quest', 'npc', 'motivation', 'chronicle'];
    public const STATUSES = ['in_progress', 'paused', 'closed'];
    public const VISIBILITIES = ['private', 'campaign', 'gm_player', 'public_campaign'];
    public const RELATION_TYPES = ['related', 'quest', 'npc', 'chronicle'];
    public const SECTION_KEYS = [
        'facts', 'team_decisions', 'clues_events', 'stakes',
        'personal_perspective', 'suspicions', 'emotions', 'motivation',
        'player_knowledge', 'appearance_behavior', 'subjective_impression',
        'obligations', 'next_conversation', 'hero_goal', 'motivation_source',
        'personal_meaning', 'boundary', 'inner_conflict', 'decision_impact',
        'change_history', 'chronicle_event', 'notes',
    ];
    public const SENSITIVE_SECTIONS = [
        'personal_perspective', 'suspicions', 'emotions', 'motivation',
        'subjective_impression', 'next_conversation', 'personal_meaning',
        'boundary', 'inner_conflict', 'notes',
    ];

    public function validateCreate(array $payload): array
    {
        return $this->validate($payload, false);
    }

    public function validateUpdate(array $payload): array
    {
        return $this->validate($payload, true);
    }

    public function validateChecklist(array $payload, bool $create): array
    {
        $errors = [];
        $data = [];
        if ($create || array_key_exists('label', $payload)) {
            $label = $this->text($payload['label'] ?? '', 300);
            if ($label === '') {
                $errors['label'] = 'Checklist label is required.';
            } else {
                $data['label'] = $label;
            }
        }
        if (array_key_exists('isCompleted', $payload) || array_key_exists('is_completed', $payload)) {
            $value = $payload['isCompleted'] ?? $payload['is_completed'];
            if (!is_bool($value) && !in_array($value, [0, 1, '0', '1'], true)) {
                $errors['isCompleted'] = 'Checklist state must be boolean.';
            } else {
                $data['is_completed'] = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
        }
        return ['valid' => !$errors, 'errors' => $errors, 'data' => $data];
    }

    public function validateRelation(array $payload): array
    {
        $target = filter_var(
            $payload['targetEntryId'] ?? $payload['target_entry_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $type = (string) ($payload['relationType'] ?? $payload['relation_type'] ?? 'related');
        $errors = [];
        if ($target === false) {
            $errors['targetEntryId'] = 'A valid target entry is required.';
        }
        if (!in_array($type, self::RELATION_TYPES, true)) {
            $errors['relationType'] = 'Relation type is invalid.';
        }
        return [
            'valid' => !$errors,
            'errors' => $errors,
            'data' => ['target_entry_id' => (int) $target, 'relation_type' => $type],
        ];
    }

    private function validate(array $payload, bool $update): array
    {
        $errors = [];
        $data = [];
        $required = static function (string $field) use ($payload, $update): bool {
            return !$update || array_key_exists($field, $payload);
        };

        if ($required('type') || array_key_exists('entryType', $payload)) {
            $type = (string) ($payload['type'] ?? $payload['entryType'] ?? '');
            if (!in_array($type, self::TYPES, true)) {
                $errors['type'] = 'Entry type is invalid.';
            } else {
                $data['entry_type'] = $type;
            }
        }
        if ($required('title')) {
            $title = $this->text($payload['title'] ?? '', 180);
            if ($title === '') {
                $errors['title'] = 'Title is required.';
            } else {
                $data['title'] = $title;
            }
        }
        foreach ([
            'status' => [self::STATUSES, 'status'],
            'visibility' => [self::VISIBILITIES, 'visibility'],
        ] as $input => [$allowed, $column]) {
            if (!$update || array_key_exists($input, $payload)) {
                $fallback = $input === 'status' ? 'in_progress' : 'private';
                $value = (string) ($payload[$input] ?? $fallback);
                if (!in_array($value, $allowed, true)) {
                    $errors[$input] = ucfirst($input) . ' is invalid.';
                } else {
                    $data[$column] = $value;
                }
            }
        }
        if (!$update || array_key_exists('summary', $payload)) {
            $data['summary'] = $this->nullableText($payload['summary'] ?? null, 500);
        }
        foreach ([
            'sessionNumber' => 'session_number',
            'trustLevel' => 'trust_level',
        ] as $input => $column) {
            if (!$update || array_key_exists($input, $payload)) {
                $data[$column] = $this->nullableInteger($payload[$input] ?? null, $input, $errors);
            }
        }
        if (isset($data['trust_level']) && ($data['trust_level'] < 0 || $data['trust_level'] > 100)) {
            $errors['trustLevel'] = 'Trust level must be between 0 and 100.';
        }
        if (!$update || array_key_exists('occurredOn', $payload)) {
            $data['occurred_on'] = $this->date($payload['occurredOn'] ?? null, 'occurredOn', $errors);
        }
        if ($update) {
            $revision = filter_var(
                $payload['revision'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($revision === false) {
                $errors['revision'] = 'A valid revision is required.';
            }
        } else {
            $revision = 1;
        }

        $nested = [];
        foreach (['sections', 'checklist', 'encounters', 'relations'] as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            if (!is_array($payload[$field]) || !array_is_list($payload[$field])) {
                $errors[$field] = ucfirst($field) . ' must be a list.';
                continue;
            }
            $validators = [
                'sections' => 'validateSections',
                'checklist' => 'validateChecklistItems',
                'encounters' => 'validateEncounterItems',
                'relations' => 'validateRelationItems',
            ];
            $nested[$field] = $this->{$validators[$field]}($payload[$field], $errors);
        }

        return [
            'valid' => !$errors,
            'errors' => $errors,
            'data' => $data,
            'revision' => (int) $revision,
            'nested' => $nested,
        ];
    }

    private function validateSections(array $items, array &$errors): array
    {
        $result = [];
        $seen = [];
        foreach (array_slice($items, 0, 40) as $index => $item) {
            if (!is_array($item)) {
                $errors["sections.{$index}"] = 'Section must be an object.';
                continue;
            }
            $key = (string) ($item['key'] ?? $item['sectionKey'] ?? '');
            if (!in_array($key, self::SECTION_KEYS, true) || isset($seen[$key])) {
                $errors["sections.{$index}.key"] = 'Section key is invalid or duplicated.';
                continue;
            }
            $seen[$key] = true;
            $visibility = $item['visibility'] ?? null;
            if ($visibility !== null && !in_array($visibility, self::VISIBILITIES, true)) {
                $errors["sections.{$index}.visibility"] = 'Section visibility is invalid.';
                continue;
            }
            if ($visibility === null && in_array($key, self::SENSITIVE_SECTIONS, true)) {
                $visibility = 'private';
            }
            $result[] = [
                'section_key' => $key,
                'content' => $this->nullableText($item['content'] ?? null, 20000),
                'visibility' => $visibility,
                'sort_order' => $index,
            ];
        }
        return $result;
    }

    private function validateChecklistItems(array $items, array &$errors): array
    {
        $result = [];
        foreach (array_slice($items, 0, 200) as $index => $item) {
            if (!is_array($item)) {
                $errors["checklist.{$index}"] = 'Checklist item must be an object.';
                continue;
            }
            $label = $this->text($item['label'] ?? '', 300);
            if ($label === '') {
                $errors["checklist.{$index}.label"] = 'Checklist label is required.';
                continue;
            }
            $result[] = [
                'id' => $this->optionalId($item['id'] ?? null),
                'label' => $label,
                'is_completed' => filter_var(
                    $item['isCompleted'] ?? $item['is_completed'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ) ? 1 : 0,
                'sort_order' => $index,
            ];
        }
        return $result;
    }

    private function validateEncounterItems(array $items, array &$errors): array
    {
        $result = [];
        foreach (array_slice($items, 0, 100) as $index => $item) {
            if (!is_array($item)) {
                $errors["encounters.{$index}"] = 'Encounter must be an object.';
                continue;
            }
            $summary = $this->text($item['summary'] ?? '', 10000);
            if ($summary === '') {
                $errors["encounters.{$index}.summary"] = 'Encounter summary is required.';
                continue;
            }
            $result[] = [
                'id' => $this->optionalId($item['id'] ?? null),
                'session_number' => $this->nullableInteger(
                    $item['sessionNumber'] ?? null,
                    "encounters.{$index}.sessionNumber",
                    $errors
                ),
                'occurred_on' => $this->date(
                    $item['occurredOn'] ?? null,
                    "encounters.{$index}.occurredOn",
                    $errors
                ),
                'summary' => $summary,
                'sort_order' => $index,
            ];
        }
        return $result;
    }

    private function validateRelationItems(array $items, array &$errors): array
    {
        $result = [];
        $seen = [];
        foreach (array_slice($items, 0, 100) as $index => $item) {
            $validated = $this->validateRelation(is_array($item) ? $item : []);
            if (!$validated['valid']) {
                foreach ($validated['errors'] as $key => $message) {
                    $errors["relations.{$index}.{$key}"] = $message;
                }
                continue;
            }
            $key = $validated['data']['target_entry_id'] . ':'
                . $validated['data']['relation_type'];
            if (isset($seen[$key])) {
                $errors["relations.{$index}"] = 'Relation is duplicated.';
                continue;
            }
            $seen[$key] = true;
            $result[] = $validated['data'];
        }
        return $result;
    }

    private function nullableInteger($value, string $field, array &$errors): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($number === false) {
            $errors[$field] = 'A non-negative integer is required.';
            return null;
        }
        return (int) $number;
    }

    private function date($value, string $field, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            $errors[$field] = 'Date must use YYYY-MM-DD.';
            return null;
        }
        return (string) $value;
    }

    private function optionalId($value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : (int) $id;
    }

    private function nullableText($value, int $limit): ?string
    {
        $text = $this->text($value, $limit);
        return $text === '' ? null : $text;
    }

    private function text($value, int $limit): string
    {
        $text = trim((string) $value);
        return mb_substr($text, 0, $limit);
    }
}
