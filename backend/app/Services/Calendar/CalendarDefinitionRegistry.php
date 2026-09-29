<?php

namespace App\Services\Calendar;

use CodeIgniter\Database\BaseConnection;

final class CalendarDefinitionRegistry
{
    private $db;
    private $definitions;

    public function __construct(?BaseConnection $db = null, ?array $definitions = null)
    {
        $this->db = $db;
        $source = $definitions ?: [require APPPATH . 'Data/Calendars/wfrp_imperial.php'];
        $this->definitions = [];
        foreach ($source as $definition) {
            $prepared = $this->prepare($definition);
            $this->definitions[$prepared['key']] = $prepared;
        }
    }

    public function get(string $key): array
    {
        if (!isset($this->definitions[$key])) {
            throw new CalendarException(
                'calendar_definition_not_found',
                'Calendar definition was not found.',
                404
            );
        }
        return $this->definitions[$key];
    }

    public function forCampaign(array $campaign): array
    {
        $settings = $campaign['settings_json'] ?? [];
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }
        $explicit = trim((string) ($settings['calendarKey'] ?? $settings['calendar_key'] ?? ''));
        if ($explicit !== '') {
            return $this->get($explicit);
        }

        $systemCodes = [strtolower(trim((string) ($campaign['system_type'] ?? '')))];
        $universeCodes = [];
        $db = $this->db ?: \Config\Database::connect();
        if (!empty($campaign['rpg_system_id']) && $db->tableExists('rpg_systems')) {
            $row = $db->table('rpg_systems')->select('code')
                ->where('id', (int) $campaign['rpg_system_id'])->get()->getRowArray();
            if ($row) {
                $systemCodes[] = strtolower((string) $row['code']);
            }
        }
        if (!empty($campaign['rpg_universe_id']) && $db->tableExists('rpg_universes')) {
            $row = $db->table('rpg_universes')->select('code')
                ->where('id', (int) $campaign['rpg_universe_id'])->get()->getRowArray();
            if ($row) {
                $universeCodes[] = strtolower((string) $row['code']);
            }
        }

        foreach ($this->definitions as $definition) {
            $match = $definition['settingMatch'];
            if (array_intersect($systemCodes, $match['systemCodes'])
                || array_intersect($universeCodes, $match['universeCodes'])) {
                return $definition;
            }
        }
        throw new CalendarException(
            'calendar_definition_unavailable',
            'This campaign setting has no registered calendar.',
            404
        );
    }

    public function publicDefinition(array $definition): array
    {
        unset($definition['settingMatch']);
        return $definition;
    }

    private function prepare(array $definition): array
    {
        $required = ['key', 'months', 'intercalaryDays', 'week', 'defaultState'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $definition)) {
                throw new \InvalidArgumentException("Calendar definition misses {$field}.");
            }
        }
        $specialByAfter = [];
        foreach ($definition['intercalaryDays'] as $special) {
            $after = $special['afterMonthKey'] ?? '__before_year__';
            $specialByAfter[$after][] = $special;
        }

        $timeline = [];
        $dayOfYear = 1;
        $numberedIndex = 0;
        $months = [];
        $specials = [];
        foreach ($specialByAfter['__before_year__'] ?? [] as $special) {
            $entry = $special + [
                'type' => 'intercalary',
                'dayOfYear' => $dayOfYear++,
                'weekdayIndex' => null,
            ];
            $specials[] = $entry;
            $timeline[] = $entry;
        }
        foreach ($definition['months'] as $monthIndex => $month) {
            $preparedMonth = $month + [
                'index' => $monthIndex,
                'startDayOfYear' => $dayOfYear,
                'startNumberedIndex' => $numberedIndex,
            ];
            $months[] = $preparedMonth;
            $timeline[] = [
                'type' => 'month',
                'key' => $month['key'],
                'name' => $month['name'],
                'length' => (int) $month['length'],
                'startDayOfYear' => $dayOfYear,
            ];
            $dayOfYear += (int) $month['length'];
            $numberedIndex += (int) $month['length'];
            foreach ($specialByAfter[$month['key']] ?? [] as $special) {
                $entry = $special + [
                    'type' => 'intercalary',
                    'dayOfYear' => $dayOfYear++,
                    'weekdayIndex' => null,
                ];
                $specials[] = $entry;
                $timeline[] = $entry;
            }
        }
        $definition['months'] = $months;
        $definition['intercalaryDays'] = $specials;
        $definition['timeline'] = $timeline;
        $definition['daysPerYear'] = $dayOfYear - 1;
        $definition['numberedDaysPerYear'] = $numberedIndex;

        foreach ($definition['seasons'] ?? [] as $index => $season) {
            $definition['seasons'][$index]['startDayOfYear'] = $this->monthDayOfYear(
                $months,
                (string) $season['start']['monthKey'],
                (int) $season['start']['day']
            );
        }
        foreach ($definition['holidays'] ?? [] as $index => $holiday) {
            if (!empty($holiday['specialDayKey'])) {
                foreach ($specials as $special) {
                    if ($special['key'] === $holiday['specialDayKey']) {
                        $definition['holidays'][$index]['dayOfYear'] = $special['dayOfYear'];
                        break;
                    }
                }
            } elseif (isset($holiday['monthKey'], $holiday['day'])) {
                $definition['holidays'][$index]['dayOfYear'] = $this->monthDayOfYear(
                    $months,
                    (string) $holiday['monthKey'],
                    (int) $holiday['day']
                );
            }
        }

        if ($definition['daysPerYear'] < 1 || $definition['numberedDaysPerYear'] < 1) {
            throw new \InvalidArgumentException('Calendar year must contain days.');
        }
        return $definition;
    }

    private function monthDayOfYear(array $months, string $key, int $day): int
    {
        foreach ($months as $month) {
            if ($month['key'] === $key && $day >= 1 && $day <= (int) $month['length']) {
                return (int) $month['startDayOfYear'] + $day - 1;
            }
        }
        throw new \InvalidArgumentException('Calendar month date is invalid.');
    }
}
