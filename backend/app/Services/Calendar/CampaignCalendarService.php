<?php

namespace App\Services\Calendar;

use App\Models\CampaignCalendarMoonOverrideModel;
use App\Services\Campaign\CampaignGuardService;
use CodeIgniter\Database\BaseConnection;

final class CampaignCalendarService
{
    private $db;
    private $guard;
    private $registry;
    private $time;
    private $events;
    private $moons;
    private $policy;
    private $revisionGuard;
    private $engine;
    private $presenter;
    private $publisher;

    public function __construct(
        ?BaseConnection $db = null,
        ?CampaignGuardService $guard = null,
        ?CalendarDefinitionRegistry $registry = null,
        ?CalendarTimeService $time = null,
        ?CalendarEventService $events = null,
        ?CampaignCalendarMoonOverrideModel $moons = null,
        ?CalendarAccessPolicy $policy = null,
        ?CalendarRevisionGuard $revisionGuard = null,
        ?CalendarDateEngine $engine = null,
        ?CalendarPresenter $presenter = null,
        ?CalendarRealtimePublisher $publisher = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->guard = $guard ?: new CampaignGuardService();
        $this->registry = $registry ?: new CalendarDefinitionRegistry($this->db);
        $this->engine = $engine ?: new CalendarDateEngine();
        $this->presenter = $presenter ?: new CalendarPresenter($this->engine);
        $this->publisher = $publisher ?: new CalendarRealtimePublisher();
        $this->time = $time ?: new CalendarTimeService(
            $this->db,
            null,
            $this->registry,
            $this->engine,
            $revisionGuard,
            $this->presenter,
            $this->publisher
        );
        $this->events = $events ?: new CalendarEventService(
            $this->db,
            null,
            $this->time,
            null,
            $policy,
            $revisionGuard,
            $this->presenter,
            $this->publisher
        );
        $this->moons = $moons ?: new CampaignCalendarMoonOverrideModel($this->db);
        $this->policy = $policy ?: new CalendarAccessPolicy();
        $this->revisionGuard = $revisionGuard ?: new CalendarRevisionGuard();
    }

    public function show(int $campaignId, array $auth): array
    {
        [$context, $definition, $row] = $this->calendarContext($campaignId, $auth);
        $moonRows = $this->moons->where('campaign_id', $campaignId)
            ->orderBy('year', 'ASC')->orderBy('day_of_year', 'ASC')->findAll();
        $moonOverrides = array_map(function (array $moon) use ($definition): array {
            return $this->presenter->moon($definition, $moon);
        }, $moonRows);
        $currentMoon = null;
        foreach ($moonOverrides as $moon) {
            if ($moon['year'] === (int) $row['year'] && $moon['dayOfYear'] === (int) $row['day_of_year']) {
                $currentMoon = $moon['phase'];
                break;
            }
        }
        return [
            'definition' => $this->registry->publicDefinition($definition),
            'state' => $this->presenter->state($definition, $row, $currentMoon),
            'moonOverrides' => $moonOverrides,
            'capabilities' => [
                'canManage' => $this->policy->canManage($context),
                'canCreateEvents' => $this->policy->canManage($context),
            ],
        ];
    }

    public function setState(int $campaignId, array $auth, array $payload): array
    {
        [$context] = $this->calendarContext($campaignId, $auth);
        $this->policy->requireManage($context);
        return ['state' => $this->time->set($context['campaign'], (int) $context['auth']['user_id'], $payload)];
    }

    public function advance(int $campaignId, array $auth, array $payload): array
    {
        [$context] = $this->calendarContext($campaignId, $auth);
        $this->policy->requireManage($context);
        return ['state' => $this->time->advance($context['campaign'], (int) $context['auth']['user_id'], $payload)];
    }

    public function listEvents(int $campaignId, array $auth, array $query): array
    {
        [$context, $definition, $row] = $this->calendarContext($campaignId, $auth);
        return $this->events->list($context, $definition, $query, (int) $row['revision']);
    }

    public function createEvent(int $campaignId, array $auth, array $payload): array
    {
        [$context, $definition] = $this->calendarContext($campaignId, $auth);
        return $this->events->create($context, $definition, $payload);
    }

    public function updateEvent(int $campaignId, int $eventId, array $auth, array $payload): array
    {
        [$context, $definition] = $this->calendarContext($campaignId, $auth);
        return $this->events->update($context, $definition, $eventId, $payload);
    }

    public function deleteEvent(int $campaignId, int $eventId, array $auth, array $payload): array
    {
        [$context] = $this->calendarContext($campaignId, $auth);
        return $this->events->delete($context, $eventId, $payload);
    }

    public function setMorrslieb(int $campaignId, array $auth, array $payload): array
    {
        [$context, $definition] = $this->calendarContext($campaignId, $auth);
        $this->policy->requireManage($context);
        $allowed = ['year', 'dayOfYear', 'phase', 'expectedRevision'];
        $unexpected = array_diff(array_keys($payload), $allowed);
        if ($unexpected) {
            throw new CalendarException('validation_failed', 'Morrslieb payload is invalid.', 422, array_fill_keys(
                $unexpected,
                'This field is not accepted.'
            ));
        }
        $year = $this->positive($payload['year'] ?? null, 'year');
        $day = $this->positive($payload['dayOfYear'] ?? null, 'dayOfYear');
        $expected = $this->positive($payload['expectedRevision'] ?? null, 'expectedRevision');
        $this->engine->assertDate($definition, $year, $day);
        if (!array_key_exists('phase', $payload)) {
            throw new CalendarException('validation_failed', 'Morrslieb phase is invalid.', 422, [
                'phase' => 'A phase key or null is required.',
            ]);
        }
        $phaseKey = $payload['phase'] === null ? null : trim((string) $payload['phase']);
        if ($phaseKey !== null) {
            $allowedPhases = array_column($definition['moonCycles']['morrslieb']['phases'] ?? [], 'key');
            if (!in_array($phaseKey, $allowedPhases, true)) {
                throw new CalendarException('validation_failed', 'Morrslieb phase is invalid.', 422, [
                    'phase' => 'Use a phase exposed by the calendar definition or null.',
                ]);
            }
        }
        $userId = (int) $context['auth']['user_id'];
        $this->db->transBegin();
        try {
            $locked = $this->time->lockState($campaignId);
            if (!$locked) {
                throw new CalendarException('calendar_not_found', 'Calendar state was not found.', 404);
            }
            $this->revisionGuard->assertExpected($expected, (int) $locked['revision']);
            $existing = $this->moons->where('campaign_id', $campaignId)
                ->where('year', $year)->where('day_of_year', $day)->first();
            $moon = null;
            if ($phaseKey === null) {
                if ($existing && !$this->moons->delete((int) $existing['id'])) {
                    throw new CalendarException(
                        'calendar_moon_write_failed',
                        'Morrslieb phase could not be removed.',
                        500
                    );
                }
            } elseif ($existing) {
                $revision = (int) $existing['revision'] + 1;
                if (!$this->moons->update((int) $existing['id'], [
                    'phase_key' => $phaseKey,
                    'revision' => $revision,
                    'updated_by_user_id' => $userId,
                ])) {
                    throw new CalendarException(
                        'calendar_moon_write_failed',
                        'Morrslieb phase could not be saved.',
                        500
                    );
                }
                $moon = $this->moons->find((int) $existing['id']);
            } else {
                if ($this->moons->insert([
                    'campaign_id' => $campaignId,
                    'year' => $year,
                    'day_of_year' => $day,
                    'phase_key' => $phaseKey,
                    'revision' => 1,
                    'updated_by_user_id' => $userId,
                ]) === false) {
                    throw new CalendarException(
                        'calendar_moon_write_failed',
                        'Morrslieb phase could not be saved.',
                        500
                    );
                }
                $moon = $this->moons->find((int) $this->moons->getInsertID());
            }
            $state = $this->time->bumpLocked($locked, $userId);
            if (!$this->db->transCommit()) {
                throw new CalendarException('calendar_moon_write_failed', 'Morrslieb phase could not be committed.', 500);
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        $presentedMoon = $moon ? $this->presenter->moon($definition, $moon) : null;
        $this->publisher->publish(
            'calendar.moon.updated',
            $campaignId,
            (int) $state['revision'],
            $userId,
            ['year' => $year, 'dayOfYear' => $day, 'moon' => $presentedMoon]
        );
        return [
            'moon' => $presentedMoon,
            'year' => $year,
            'dayOfYear' => $day,
            'revision' => (int) $state['revision'],
        ];
    }

    private function calendarContext(int $campaignId, array $auth): array
    {
        if ($campaignId < 1) {
            throw new CalendarException('campaign_not_found', 'Campaign was not found.', 404);
        }
        try {
            $context = $this->guard->context($auth, $campaignId);
        } catch (\App\Services\Campaign\CampaignException $exception) {
            throw new CalendarException(
                $exception->errorCode(),
                $exception->getMessage(),
                $exception->status(),
                $exception->details()
            );
        }
        $calendar = $this->time->getOrCreate($context['campaign'], (int) $context['auth']['user_id']);
        return [$context, $calendar['definition'], $calendar['state']];
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
