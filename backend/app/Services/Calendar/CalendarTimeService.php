<?php

namespace App\Services\Calendar;

use App\Models\CampaignCalendarModel;
use CodeIgniter\Database\BaseConnection;

final class CalendarTimeService
{
    private $db;
    private $states;
    private $registry;
    private $engine;
    private $revisionGuard;
    private $presenter;
    private $publisher;

    public function __construct(
        ?BaseConnection $db = null,
        ?CampaignCalendarModel $states = null,
        ?CalendarDefinitionRegistry $registry = null,
        ?CalendarDateEngine $engine = null,
        ?CalendarRevisionGuard $revisionGuard = null,
        ?CalendarPresenter $presenter = null,
        ?CalendarRealtimePublisher $publisher = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->states = $states ?: new CampaignCalendarModel($this->db);
        $this->registry = $registry ?: new CalendarDefinitionRegistry($this->db);
        $this->engine = $engine ?: new CalendarDateEngine();
        $this->revisionGuard = $revisionGuard ?: new CalendarRevisionGuard();
        $this->presenter = $presenter ?: new CalendarPresenter($this->engine);
        $this->publisher = $publisher ?: new CalendarRealtimePublisher();
    }

    public function getOrCreate(array $campaign, int $userId): array
    {
        $campaignId = (int) $campaign['id'];
        $row = $this->states->find($campaignId);
        if ($row) {
            return ['definition' => $this->registry->get((string) $row['calendar_key']), 'state' => $row];
        }
        $definition = $this->registry->forCampaign($campaign);
        $default = $definition['defaultState'];
        $now = date('Y-m-d H:i:s');
        $insert = [
            'campaign_id' => $campaignId,
            'calendar_key' => $definition['key'],
            'year' => (int) $default['year'],
            'day_of_year' => (int) $default['dayOfYear'],
            'minute_of_day' => (int) $default['minuteOfDay'],
            'show_time' => !array_key_exists('showTime', $default) || !empty($default['showTime']) ? 1 : 0,
            'is_running' => !empty($default['running']) ? 1 : 0,
            'revision' => 1,
            'updated_by_user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->db->transBegin();
        try {
            $existing = $this->lockState($campaignId);
            if (!$existing) {
                if (!$this->db->table('campaign_calendars')->insert($insert)) {
                    throw new CalendarException('calendar_write_failed', 'Calendar state could not be created.', 500);
                }
                $existing = $insert;
            }
            if (!$this->db->transCommit()) {
                throw new CalendarException('calendar_write_failed', 'Calendar state could not be committed.', 500);
            }
            return [
                'definition' => $this->registry->get((string) $existing['calendar_key']),
                'state' => $existing,
            ];
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function set(array $campaign, int $userId, array $payload): array
    {
        $allowed = ['year', 'dayOfYear', 'minuteOfDay', 'time', 'showTime', 'running', 'expectedRevision'];
        $this->assertExactKeys($payload, $allowed);
        $context = $this->getOrCreate($campaign, $userId);
        $definition = $context['definition'];
        $year = $this->positiveInteger($payload['year'] ?? null, 'year');
        $day = $this->positiveInteger($payload['dayOfYear'] ?? null, 'dayOfYear');
        $minute = array_key_exists('time', $payload)
            ? $this->parseTime($payload['time'])
            : $this->nonNegativeInteger($payload['minuteOfDay'] ?? null, 'minuteOfDay');
        $this->engine->assertDate($definition, $year, $day, $minute);
        $running = array_key_exists('running', $payload)
            ? $this->boolean($payload['running'], 'running')
            : null;
        $showTime = array_key_exists('showTime', $payload)
            ? $this->boolean($payload['showTime'], 'showTime')
            : null;
        $result = $this->mutate(
            (int) $campaign['id'],
            $userId,
            $this->positiveInteger($payload['expectedRevision'] ?? null, 'expectedRevision'),
            static function (array $locked) use ($year, $day, $minute, $showTime, $running): array {
                return [
                    'year' => $year,
                    'day_of_year' => $day,
                    'minute_of_day' => $minute,
                    'show_time' => $showTime === null ? (int) ($locked['show_time'] ?? 1) : ($showTime ? 1 : 0),
                    'is_running' => $running === null ? (int) $locked['is_running'] : ($running ? 1 : 0),
                ];
            }
        );
        $presented = $this->presenter->state($definition, $result);
        $this->publisher->publish(
            'calendar.state.updated',
            (int) $campaign['id'],
            (int) $result['revision'],
            $userId,
            ['state' => $presented]
        );
        return $presented;
    }

    public function advance(array $campaign, int $userId, array $payload): array
    {
        $this->assertExactKeys($payload, ['amount', 'unit', 'expectedRevision']);
        $context = $this->getOrCreate($campaign, $userId);
        $definition = $context['definition'];
        $unit = (string) ($payload['unit'] ?? '');
        $amount = $unit === 'nextDay' ? 1 : $this->integer($payload['amount'] ?? null, 'amount');
        $result = $this->mutate(
            (int) $campaign['id'],
            $userId,
            $this->positiveInteger($payload['expectedRevision'] ?? null, 'expectedRevision'),
            function (array $locked) use ($definition, $amount, $unit): array {
                $next = $this->engine->advance($definition, $locked, $amount, $unit);
                return [
                    'year' => $next['year'],
                    'day_of_year' => $next['dayOfYear'],
                    'minute_of_day' => $next['minuteOfDay'],
                    'show_time' => (int) ($locked['show_time'] ?? 1),
                    'is_running' => (int) $locked['is_running'],
                ];
            }
        );
        $presented = $this->presenter->state($definition, $result);
        $this->publisher->publish(
            'calendar.state.updated',
            (int) $campaign['id'],
            (int) $result['revision'],
            $userId,
            ['state' => $presented]
        );
        return $presented;
    }

    public function lockState(int $campaignId): ?array
    {
        $table = $this->db->prefixTable('campaign_calendars');
        return $this->db->query(
            "SELECT * FROM {$table} WHERE campaign_id = ? FOR UPDATE",
            [$campaignId]
        )->getRowArray() ?: null;
    }

    public function bumpLocked(array $locked, int $userId): array
    {
        $revision = (int) $locked['revision'] + 1;
        $updatedAt = date('Y-m-d H:i:s');
        $updated = $this->db->table('campaign_calendars')
            ->where('campaign_id', (int) $locked['campaign_id'])
            ->where('revision', (int) $locked['revision'])
            ->update([
                'revision' => $revision,
                'updated_by_user_id' => $userId,
                'updated_at' => $updatedAt,
            ]);
        if (!$updated || $this->db->affectedRows() !== 1) {
            throw new CalendarException('calendar_revision_conflict', 'Calendar changed in another session.', 409);
        }
        $locked['revision'] = $revision;
        $locked['updated_by_user_id'] = $userId;
        $locked['updated_at'] = $updatedAt;
        return $locked;
    }

    private function mutate(int $campaignId, int $userId, int $expectedRevision, callable $changes): array
    {
        $this->db->transBegin();
        try {
            $locked = $this->lockState($campaignId);
            if (!$locked) {
                throw new CalendarException('calendar_not_found', 'Calendar state was not found.', 404);
            }
            $this->revisionGuard->assertExpected($expectedRevision, (int) $locked['revision']);
            $data = $changes($locked);
            $data['revision'] = (int) $locked['revision'] + 1;
            $data['updated_by_user_id'] = $userId;
            $data['updated_at'] = date('Y-m-d H:i:s');
            $updated = $this->db->table('campaign_calendars')
                ->where('campaign_id', $campaignId)
                ->where('revision', (int) $locked['revision'])
                ->update($data);
            if (!$updated || $this->db->affectedRows() !== 1) {
                throw new CalendarException('calendar_revision_conflict', 'Calendar changed in another session.', 409);
            }
            $row = array_merge($locked, $data);
            if (!$this->db->transCommit()) {
                throw new CalendarException('calendar_write_failed', 'Calendar state could not be committed.', 500);
            }
            return $row;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    private function assertExactKeys(array $payload, array $allowed): void
    {
        $unexpected = array_diff(array_keys($payload), $allowed);
        if ($unexpected) {
            throw new CalendarException(
                'validation_failed',
                'Calendar payload is invalid.',
                422,
                array_fill_keys($unexpected, 'This field is not accepted.')
            );
        }
    }

    private function positiveInteger($value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($integer === false) {
            throw new CalendarException('validation_failed', 'Calendar payload is invalid.', 422, [
                $field => 'A positive integer is required.',
            ]);
        }
        return (int) $integer;
    }

    private function nonNegativeInteger($value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($integer === false) {
            throw new CalendarException('validation_failed', 'Calendar payload is invalid.', 422, [
                $field => 'A non-negative integer is required.',
            ]);
        }
        return (int) $integer;
    }

    private function integer($value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false) {
            throw new CalendarException('validation_failed', 'Calendar payload is invalid.', 422, [
                $field => 'An integer is required.',
            ]);
        }
        return (int) $integer;
    }

    private function parseTime($value): int
    {
        if (!is_string($value) || !preg_match('/^(\d{2}):(\d{2})$/', $value, $match)) {
            throw new CalendarException('validation_failed', 'Calendar payload is invalid.', 422, [
                'time' => 'Use HH:MM time.',
            ]);
        }
        $hour = (int) $match[1];
        $minute = (int) $match[2];
        if ($hour > 23 || $minute > 59) {
            throw new CalendarException('validation_failed', 'Calendar payload is invalid.', 422, [
                'time' => 'Time must be between 00:00 and 23:59.',
            ]);
        }
        return $hour * 60 + $minute;
    }

    private function boolean($value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        throw new CalendarException('validation_failed', 'Calendar payload is invalid.', 422, [
            $field => 'A boolean value is required.',
        ]);
    }
}
