<?php

namespace App\Services\Calendar;

use App\Models\CampaignCalendarEventModel;
use CodeIgniter\Database\BaseConnection;

final class CalendarEventService
{
    private $db;
    private $events;
    private $time;
    private $validator;
    private $policy;
    private $revisionGuard;
    private $presenter;
    private $publisher;

    public function __construct(
        ?BaseConnection $db = null,
        ?CampaignCalendarEventModel $events = null,
        ?CalendarTimeService $time = null,
        ?CalendarEventValidator $validator = null,
        ?CalendarAccessPolicy $policy = null,
        ?CalendarRevisionGuard $revisionGuard = null,
        ?CalendarPresenter $presenter = null,
        ?CalendarRealtimePublisher $publisher = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->events = $events ?: new CampaignCalendarEventModel($this->db);
        $this->time = $time ?: new CalendarTimeService($this->db);
        $this->validator = $validator ?: new CalendarEventValidator();
        $this->policy = $policy ?: new CalendarAccessPolicy();
        $this->revisionGuard = $revisionGuard ?: new CalendarRevisionGuard();
        $this->presenter = $presenter ?: new CalendarPresenter();
        $this->publisher = $publisher ?: new CalendarRealtimePublisher();
    }

    public function list(array $context, array $definition, array $query, int $calendarRevision): array
    {
        $this->assertExactKeys($query, ['fromYear', 'fromDay', 'toYear', 'toDay']);
        $fromYear = $this->positive($query['fromYear'] ?? null, 'fromYear');
        $fromDay = $this->positive($query['fromDay'] ?? 1, 'fromDay');
        $toYear = $this->positive($query['toYear'] ?? $fromYear, 'toYear');
        $toDay = $this->positive($query['toDay'] ?? $definition['daysPerYear'], 'toDay');
        $engine = new CalendarDateEngine();
        $engine->assertDate($definition, $fromYear, $fromDay);
        $engine->assertDate($definition, $toYear, $toDay, 1439);
        if ($engine->compare($fromYear, $fromDay, 0, $toYear, $toDay, 1439) > 0) {
            throw new CalendarException('validation_failed', 'Calendar event range is invalid.', 422, [
                'range' => 'Range end cannot precede its start.',
            ]);
        }
        if ($toYear - $fromYear > 10) {
            throw new CalendarException('validation_failed', 'Calendar event range is invalid.', 422, [
                'range' => 'A single request may cover at most eleven calendar years.',
            ]);
        }
        $rows = $this->events->where('campaign_id', (int) $context['campaign']['id'])
            ->orderBy('start_year', 'ASC')->orderBy('start_day_of_year', 'ASC')
            ->orderBy('start_minute', 'ASC')->findAll();
        $participants = $this->participantsByEvent(array_column($rows, 'id'));
        $items = [];
        foreach ($rows as $row) {
            $ids = $participants[(int) $row['id']] ?? [];
            if (!$this->policy->canViewEvent($row, $context, $ids)) {
                continue;
            }
            foreach ($this->occurrences($row, $fromYear, $fromDay, $toYear, $toDay, (int) $definition['daysPerYear']) as $occurrence) {
                $items[] = $this->presenter->event($definition, $occurrence, $ids);
            }
        }
        usort($items, static function (array $a, array $b): int {
            return [$a['start']['year'], $a['start']['dayOfYear'], $a['start']['minuteOfDay']]
                <=> [$b['start']['year'], $b['start']['dayOfYear'], $b['start']['minuteOfDay']];
        });
        return ['events' => $items, 'revision' => $calendarRevision];
    }

    public function create(array $context, array $definition, array $payload): array
    {
        $this->policy->requireManage($context);
        $this->assertExactKeys($payload, [
            'title', 'description', 'type', 'start', 'end', 'color',
            'visibility', 'participantUserIds', 'allDay', 'repeatYearly',
            'expectedRevision',
        ]);
        $expected = $this->positive($payload['expectedRevision'] ?? null, 'expectedRevision');
        $data = $this->validator->validate($definition, $payload);
        $participantIds = $data['participant_user_ids'];
        unset($data['participant_user_ids']);
        $this->assertParticipants((int) $context['campaign']['id'], $participantIds, $context);
        $userId = (int) $context['auth']['user_id'];
        $campaignId = (int) $context['campaign']['id'];
        $this->db->transBegin();
        try {
            $locked = $this->requiredLockedState($campaignId);
            $this->revisionGuard->assertExpected($expected, (int) $locked['revision']);
            $data += [
                'campaign_id' => $campaignId,
                'revision' => 1,
                'created_by_user_id' => $userId,
                'updated_by_user_id' => $userId,
            ];
            if ($this->events->insert($data) === false) {
                throw new CalendarException('calendar_event_write_failed', 'Calendar event could not be saved.', 500);
            }
            $eventId = (int) $this->events->getInsertID();
            $this->replaceParticipants($eventId, $participantIds);
            $state = $this->time->bumpLocked($locked, $userId);
            if (!$this->db->transCommit()) {
                throw new CalendarException('calendar_event_write_failed', 'Calendar event could not be committed.', 500);
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        $event = $this->events->find($eventId);
        $presented = $this->presenter->event($definition, $event, $participantIds);
        $this->publish('calendar.event.created', $campaignId, (int) $state['revision'], $userId, $eventId);
        return ['event' => $presented, 'revision' => (int) $state['revision']];
    }

    public function update(array $context, array $definition, int $eventId, array $payload): array
    {
        $this->policy->requireManage($context);
        $this->assertExactKeys($payload, [
            'title', 'description', 'type', 'start', 'end', 'color',
            'visibility', 'participantUserIds', 'allDay', 'repeatYearly',
            'expectedRevision', 'eventRevision',
        ]);
        $campaignId = (int) $context['campaign']['id'];
        $existing = $this->findEvent($campaignId, $eventId);
        $eventRevision = $this->positive($payload['eventRevision'] ?? null, 'eventRevision');
        if ($eventRevision !== (int) $existing['revision']) {
            throw new CalendarException('calendar_event_conflict', 'Calendar event changed in another session.', 409);
        }
        $participants = $this->participantsByEvent([$eventId])[$eventId] ?? [];
        $merged = array_merge($this->inputFromRow($existing, $participants), $payload);
        $data = $this->validator->validate($definition, $merged);
        $participantIds = $data['participant_user_ids'];
        unset($data['participant_user_ids']);
        $this->assertParticipants($campaignId, $participantIds, $context);
        $expected = $this->positive($payload['expectedRevision'] ?? null, 'expectedRevision');
        $userId = (int) $context['auth']['user_id'];
        $this->db->transBegin();
        try {
            $locked = $this->requiredLockedState($campaignId);
            $this->revisionGuard->assertExpected($expected, (int) $locked['revision']);
            $data['revision'] = $eventRevision + 1;
            $data['updated_by_user_id'] = $userId;
            $updated = $this->db->table('campaign_calendar_events')
                ->where('id', $eventId)->where('campaign_id', $campaignId)
                ->where('revision', $eventRevision)->update($data);
            if (!$updated || $this->db->affectedRows() !== 1) {
                throw new CalendarException('calendar_event_conflict', 'Calendar event changed in another session.', 409);
            }
            $this->replaceParticipants($eventId, $participantIds);
            $state = $this->time->bumpLocked($locked, $userId);
            if (!$this->db->transCommit()) {
                throw new CalendarException('calendar_event_write_failed', 'Calendar event could not be committed.', 500);
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        $event = $this->events->find($eventId);
        $presented = $this->presenter->event($definition, $event, $participantIds);
        $this->publish('calendar.event.updated', $campaignId, (int) $state['revision'], $userId, $eventId);
        return ['event' => $presented, 'revision' => (int) $state['revision']];
    }

    public function delete(array $context, int $eventId, array $payload): array
    {
        $this->policy->requireManage($context);
        foreach (array_diff(array_keys($payload), ['expectedRevision', 'eventRevision']) as $field) {
            throw new CalendarException('validation_failed', 'Calendar event delete payload is invalid.', 422, [
                $field => 'This field is not accepted.',
            ]);
        }
        $campaignId = (int) $context['campaign']['id'];
        $existing = $this->findEvent($campaignId, $eventId);
        $eventRevision = $this->positive($payload['eventRevision'] ?? null, 'eventRevision');
        if ($eventRevision !== (int) $existing['revision']) {
            throw new CalendarException('calendar_event_conflict', 'Calendar event changed in another session.', 409);
        }
        $expected = $this->positive($payload['expectedRevision'] ?? null, 'expectedRevision');
        $userId = (int) $context['auth']['user_id'];
        $this->db->transBegin();
        try {
            $locked = $this->requiredLockedState($campaignId);
            $this->revisionGuard->assertExpected($expected, (int) $locked['revision']);
            $deleted = $this->db->table('campaign_calendar_events')
                ->where('id', $eventId)->where('campaign_id', $campaignId)
                ->where('revision', $eventRevision)->delete();
            if (!$deleted || $this->db->affectedRows() !== 1) {
                throw new CalendarException('calendar_event_conflict', 'Calendar event changed in another session.', 409);
            }
            $state = $this->time->bumpLocked($locked, $userId);
            if (!$this->db->transCommit()) {
                throw new CalendarException('calendar_event_write_failed', 'Calendar event could not be committed.', 500);
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        $this->publish('calendar.event.deleted', $campaignId, (int) $state['revision'], $userId, $eventId);
        return ['deleted' => true, 'eventId' => $eventId, 'revision' => (int) $state['revision']];
    }

    private function occurrences(array $row, int $fromYear, int $fromDay, int $toYear, int $toDay, int $daysPerYear): array
    {
        if (empty($row['repeat_yearly'])) {
            $start = ((int) $row['start_year'] - 1) * $daysPerYear + (int) $row['start_day_of_year'];
            $endYear = (int) ($row['end_year'] ?? $row['start_year']);
            $endDay = (int) ($row['end_day_of_year'] ?? $row['start_day_of_year']);
            $end = ($endYear - 1) * $daysPerYear + $endDay;
            $rangeStart = ($fromYear - 1) * $daysPerYear + $fromDay;
            $rangeEnd = ($toYear - 1) * $daysPerYear + $toDay;
            return $start <= $rangeEnd && $end >= $rangeStart ? [$row] : [];
        }
        $durationDays = (int) ($row['end_day_of_year'] ?? $row['start_day_of_year'])
            - (int) $row['start_day_of_year'];
        $items = [];
        for ($year = $fromYear; $year <= $toYear; ++$year) {
            $startDay = (int) $row['start_day_of_year'];
            $endDay = min($daysPerYear, $startDay + max(0, $durationDays));
            if (($year === $fromYear && $endDay < $fromDay)
                || ($year === $toYear && $startDay > $toDay)) {
                continue;
            }
            $copy = $row;
            $copy['start_year'] = $year;
            if ($copy['end_year'] !== null) {
                $copy['end_year'] = $year;
                $copy['end_day_of_year'] = $endDay;
            }
            $items[] = $copy;
        }
        return $items;
    }

    private function inputFromRow(array $row, array $participants): array
    {
        $allDay = !empty($row['all_day']);
        return [
            'title' => $row['title'],
            'description' => $row['description'],
            'type' => $row['event_type'],
            'start' => [
                'year' => (int) $row['start_year'],
                'dayOfYear' => (int) $row['start_day_of_year'],
                'minute' => $allDay ? 0 : (int) $row['start_minute'],
            ],
            'end' => $row['end_year'] === null ? null : [
                'year' => (int) $row['end_year'],
                'dayOfYear' => (int) $row['end_day_of_year'],
                'minute' => $allDay ? 0 : (int) ($row['end_minute'] ?? 0),
            ],
            'color' => $row['color'],
            'visibility' => $row['visibility'],
            'participantUserIds' => $participants,
            'allDay' => $allDay,
            'repeatYearly' => !empty($row['repeat_yearly']),
        ];
    }

    private function requiredLockedState(int $campaignId): array
    {
        $row = $this->time->lockState($campaignId);
        if (!$row) {
            throw new CalendarException('calendar_not_found', 'Calendar state was not found.', 404);
        }
        return $row;
    }

    private function findEvent(int $campaignId, int $eventId): array
    {
        $event = $this->events->where('campaign_id', $campaignId)->where('id', $eventId)->first();
        if (!$event) {
            throw new CalendarException('calendar_event_not_found', 'Calendar event was not found.', 404);
        }
        return $event;
    }

    private function participantsByEvent(array $eventIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $eventIds)));
        if (!$ids) {
            return [];
        }
        $rows = $this->db->table('campaign_calendar_event_participants')
            ->select('event_id, user_id')->whereIn('event_id', $ids)->get()->getResultArray();
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['event_id']][] = (int) $row['user_id'];
        }
        return $result;
    }

    private function replaceParticipants(int $eventId, array $participantIds): void
    {
        $this->db->table('campaign_calendar_event_participants')->where('event_id', $eventId)->delete();
        if (!$participantIds) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $rows = array_map(static function (int $userId) use ($eventId, $now): array {
            return ['event_id' => $eventId, 'user_id' => $userId, 'created_at' => $now];
        }, $participantIds);
        if (!$this->db->table('campaign_calendar_event_participants')->insertBatch($rows)) {
            throw new CalendarException('calendar_event_write_failed', 'Event participants could not be saved.', 500);
        }
    }

    private function assertParticipants(int $campaignId, array $participantIds, array $context): void
    {
        if (!$participantIds) {
            return;
        }
        $rows = $this->db->table('campaign_members')->select('user_id')
            ->where('campaign_id', $campaignId)->where('is_active', 1)
            ->whereIn('user_id', $participantIds)->get()->getResultArray();
        $valid = array_map(static function (array $row): int {
            return (int) $row['user_id'];
        }, $rows);
        $valid[] = (int) $context['campaign']['game_master_id'];
        if (array_diff($participantIds, array_unique($valid))) {
            throw new CalendarException('validation_failed', 'Event participants are invalid.', 422, [
                'participantUserIds' => 'Every participant must be an active campaign member.',
            ]);
        }
    }

    private function publish(string $type, int $campaignId, int $revision, int $userId, int $eventId): void
    {
        $this->publisher->publish($type, $campaignId, $revision, $userId, ['eventId' => $eventId]);
    }

    private function assertExactKeys(array $payload, array $allowed): void
    {
        $unexpected = array_diff(array_keys($payload), $allowed);
        if ($unexpected) {
            throw new CalendarException(
                'validation_failed',
                'Calendar event payload is invalid.',
                422,
                array_fill_keys($unexpected, 'This field is not accepted.')
            );
        }
    }

    private function positive($value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($integer === false) {
            throw new CalendarException('validation_failed', 'Calendar value is invalid.', 422, [
                $field => 'A positive integer is required.',
            ]);
        }
        return (int) $integer;
    }
}
