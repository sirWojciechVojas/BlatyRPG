<?php

namespace App\Services\Calendar;

final class CalendarEventValidator
{
    public const TYPES = ['story', 'holiday', 'travel', 'quest', 'combat', 'private-note'];
    public const VISIBILITIES = ['all', 'gm', 'participants'];

    private $engine;

    public function __construct(?CalendarDateEngine $engine = null)
    {
        $this->engine = $engine ?: new CalendarDateEngine();
    }

    public function validate(array $definition, array $payload): array
    {
        $allowed = [
            'title', 'description', 'type', 'start', 'end', 'color',
            'visibility', 'participantUserIds', 'allDay', 'repeatYearly',
            'expectedRevision', 'eventRevision',
        ];
        $errors = [];
        foreach (array_diff(array_keys($payload), $allowed) as $field) {
            $errors[$field] = 'This field is not accepted.';
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 180) {
            $errors['title'] = 'Title is required and may contain at most 180 characters.';
        }
        $description = trim((string) ($payload['description'] ?? ''));
        if (mb_strlen($description) > 20000) {
            $errors['description'] = 'Description may contain at most 20000 characters.';
        }
        $type = (string) ($payload['type'] ?? 'story');
        if (!in_array($type, self::TYPES, true)) {
            $errors['type'] = 'Event type is invalid.';
        }
        $visibility = (string) ($payload['visibility'] ?? 'all');
        if (!in_array($visibility, self::VISIBILITIES, true)) {
            $errors['visibility'] = 'Event visibility is invalid.';
        }
        $color = strtolower(trim((string) ($payload['color'] ?? '#7b5b38')));
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            $errors['color'] = 'Color must use #RRGGBB notation.';
        }
        $allDay = $this->boolean($payload['allDay'] ?? true, 'allDay', $errors);
        $repeatYearly = $this->boolean($payload['repeatYearly'] ?? false, 'repeatYearly', $errors);
        $start = $this->date($definition, $payload['start'] ?? null, !$allDay, 'start', $errors);
        $end = !array_key_exists('end', $payload) || $payload['end'] === null
            ? null
            : $this->date($definition, $payload['end'], !$allDay, 'end', $errors);
        if ($start && $end && $this->engine->compare(
            $start['year'],
            $start['dayOfYear'],
            $start['minute'] ?? 0,
            $end['year'],
            $end['dayOfYear'],
            $end['minute'] ?? 1439
        ) > 0) {
            $errors['end'] = 'Event end cannot be earlier than its start.';
        }
        if ($repeatYearly && $start && $end && $start['year'] !== $end['year']) {
            $errors['end'] = 'A yearly event must end in the same calendar year in which it starts.';
        }

        $participants = $payload['participantUserIds'] ?? [];
        if (!is_array($participants) || !array_is_list($participants)) {
            $errors['participantUserIds'] = 'Participants must be a list.';
            $participants = [];
        } elseif (count($participants) > 200) {
            $errors['participantUserIds'] = 'At most 200 participants may be selected.';
        }
        $participantIds = [];
        foreach (array_slice($participants, 0, 200) as $index => $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                $errors["participantUserIds.{$index}"] = 'Participant identifier is invalid.';
            } else {
                $participantIds[] = (int) $id;
            }
        }
        $participantIds = array_values(array_unique($participantIds));
        if ($visibility === 'participants' && !$participantIds) {
            $errors['participantUserIds'] = 'Select at least one participant.';
        }

        if ($errors) {
            throw new CalendarException('validation_failed', 'Calendar event is invalid.', 422, $errors);
        }
        return [
            'title' => $title,
            'description' => $description === '' ? null : $description,
            'event_type' => $type,
            'start_year' => $start['year'],
            'start_day_of_year' => $start['dayOfYear'],
            'start_minute' => $allDay ? null : $start['minute'],
            'end_year' => $end['year'] ?? null,
            'end_day_of_year' => $end['dayOfYear'] ?? null,
            'end_minute' => $end === null || $allDay ? null : $end['minute'],
            'color' => $color,
            'visibility' => $visibility,
            'all_day' => $allDay ? 1 : 0,
            'repeat_yearly' => $repeatYearly ? 1 : 0,
            'participant_user_ids' => $participantIds,
        ];
    }

    private function date(array $definition, $value, bool $timeRequired, string $field, array &$errors): ?array
    {
        if (!is_array($value) || array_is_list($value)) {
            $errors[$field] = 'A calendar date object is required.';
            return null;
        }
        $unexpected = array_diff(array_keys($value), ['year', 'dayOfYear', 'minute']);
        foreach ($unexpected as $key) {
            $errors["{$field}.{$key}"] = 'This field is not accepted.';
        }
        $year = filter_var($value['year'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $day = filter_var($value['dayOfYear'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $minute = $timeRequired
            ? filter_var($value['minute'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
            : 0;
        if ($year === false || $day === false || ($timeRequired && $minute === false)) {
            $errors[$field] = 'Calendar date fields are invalid.';
            return null;
        }
        try {
            $this->engine->assertDate($definition, (int) $year, (int) $day, (int) $minute);
        } catch (CalendarException $exception) {
            $errors[$field] = 'Calendar date is outside the supported range.';
            return null;
        }
        return ['year' => (int) $year, 'dayOfYear' => (int) $day, 'minute' => (int) $minute];
    }

    private function boolean($value, string $field, array &$errors): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (in_array($value, [0, 1, '0', '1'], true)) {
            return (bool) $value;
        }
        $errors[$field] = 'A boolean value is required.';
        return false;
    }
}
