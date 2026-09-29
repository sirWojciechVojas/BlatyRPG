<?php

namespace App\Services\Calendar;

final class CalendarPresenter
{
    private $engine;

    public function __construct(?CalendarDateEngine $engine = null)
    {
        $this->engine = $engine ?: new CalendarDateEngine();
    }

    public function state(array $definition, array $row, ?array $morrslieb = null): array
    {
        $date = $this->engine->describe(
            $definition,
            (int) $row['year'],
            (int) $row['day_of_year'],
            (int) $row['minute_of_day']
        );
        $date['morrslieb'] = $morrslieb;
        return [
            'campaignId' => (int) $row['campaign_id'],
            'calendarKey' => (string) $row['calendar_key'],
            'year' => (int) $row['year'],
            'dayOfYear' => (int) $row['day_of_year'],
            'minuteOfDay' => (int) $row['minute_of_day'],
            'showTime' => !array_key_exists('show_time', $row) || !empty($row['show_time']),
            'running' => !empty($row['is_running']),
            'revision' => (int) $row['revision'],
            'updatedByUserId' => isset($row['updated_by_user_id']) ? (int) $row['updated_by_user_id'] : null,
            'updatedAt' => $row['updated_at'] ?? null,
            'date' => $date,
        ];
    }

    public function event(array $definition, array $row, array $participantIds = []): array
    {
        $allDay = !empty($row['all_day']);
        $startMinute = $allDay ? 0 : (int) $row['start_minute'];
        $start = $this->engine->describe(
            $definition,
            (int) $row['start_year'],
            (int) $row['start_day_of_year'],
            $startMinute
        );
        $end = null;
        if ($row['end_year'] !== null && $row['end_day_of_year'] !== null) {
            $end = $this->engine->describe(
                $definition,
                (int) $row['end_year'],
                (int) $row['end_day_of_year'],
                $allDay ? 0 : (int) ($row['end_minute'] ?? 0)
            );
        }
        return [
            'id' => (int) $row['id'],
            'campaignId' => (int) $row['campaign_id'],
            'title' => (string) $row['title'],
            'description' => $row['description'] ?? null,
            'type' => (string) $row['event_type'],
            'start' => $start,
            'end' => $end,
            'color' => (string) $row['color'],
            'visibility' => (string) $row['visibility'],
            'participantUserIds' => array_values(array_map('intval', $participantIds)),
            'allDay' => $allDay,
            'repeatYearly' => !empty($row['repeat_yearly']),
            'revision' => (int) $row['revision'],
            'createdByUserId' => (int) $row['created_by_user_id'],
            'updatedByUserId' => (int) $row['updated_by_user_id'],
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    public function moon(array $definition, array $row): array
    {
        $phase = null;
        foreach ($definition['moonCycles']['morrslieb']['phases'] ?? [] as $candidate) {
            if ($candidate['key'] === $row['phase_key']) {
                $phase = $candidate;
                break;
            }
        }
        return [
            'id' => (int) $row['id'],
            'campaignId' => (int) $row['campaign_id'],
            'year' => (int) $row['year'],
            'dayOfYear' => (int) $row['day_of_year'],
            'phase' => $phase,
            'revision' => (int) $row['revision'],
            'updatedByUserId' => (int) $row['updated_by_user_id'],
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }
}
