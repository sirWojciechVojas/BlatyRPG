<?php

namespace App\Services\Calendar;

final class CalendarDateEngine
{
    public function assertDate(array $definition, int $year, int $dayOfYear, int $minuteOfDay = 0): void
    {
        $errors = [];
        if ($year < 1 || $year > 999999) {
            $errors['year'] = 'Year must be between 1 and 999999.';
        }
        if ($dayOfYear < 1 || $dayOfYear > (int) $definition['daysPerYear']) {
            $errors['dayOfYear'] = 'Day is outside this calendar year.';
        }
        if ($minuteOfDay < 0 || $minuteOfDay > 1439) {
            $errors['minuteOfDay'] = 'Time must be between 00:00 and 23:59.';
        }
        if ($errors) {
            throw new CalendarException('validation_failed', 'Calendar date is invalid.', 422, $errors);
        }
    }

    public function dayOfYear(array $definition, string $monthKey, int $day): int
    {
        foreach ($definition['months'] as $month) {
            if ($month['key'] === $monthKey) {
                if ($day < 1 || $day > (int) $month['length']) {
                    throw new CalendarException(
                        'validation_failed',
                        'Calendar day is outside the selected month.',
                        422,
                        ['day' => 'Day is outside the selected month.']
                    );
                }
                return (int) $month['startDayOfYear'] + $day - 1;
            }
        }
        throw new CalendarException(
            'validation_failed',
            'Calendar month is invalid.',
            422,
            ['monthKey' => 'Unknown month.']
        );
    }

    public function specialDayOfYear(array $definition, string $specialKey): int
    {
        foreach ($definition['intercalaryDays'] as $special) {
            if ($special['key'] === $specialKey) {
                return (int) $special['dayOfYear'];
            }
        }
        throw new CalendarException(
            'validation_failed',
            'Intercalary day is invalid.',
            422,
            ['specialDayKey' => 'Unknown intercalary day.']
        );
    }

    public function advance(array $definition, array $state, int $amount, string $unit): array
    {
        $year = (int) $state['year'];
        $day = (int) ($state['day_of_year'] ?? $state['dayOfYear']);
        $minute = (int) ($state['minute_of_day'] ?? $state['minuteOfDay']);
        $this->assertDate($definition, $year, $day, $minute);
        if ($unit === 'nextDay') {
            $amount = 1;
            $unit = 'days';
            $minute = 0;
        }
        $multipliers = ['minutes' => 1, 'hours' => 60, 'days' => 1440];
        if (!isset($multipliers[$unit])) {
            throw new CalendarException(
                'validation_failed',
                'Calendar shift unit is invalid.',
                422,
                ['unit' => 'Use minutes, hours, days or nextDay.']
            );
        }
        if ($amount < -1000000 || $amount > 1000000) {
            throw new CalendarException(
                'validation_failed',
                'Calendar shift is outside the supported range.',
                422,
                ['amount' => 'Amount is outside the supported range.']
            );
        }
        $daysPerYear = (int) $definition['daysPerYear'];
        $absoluteMinutes = (($year - 1) * $daysPerYear + ($day - 1)) * 1440 + $minute;
        $absoluteMinutes += $amount * $multipliers[$unit];
        if ($absoluteMinutes < 0) {
            throw new CalendarException(
                'validation_failed',
                'Calendar cannot move before year 1.',
                422,
                ['amount' => 'The resulting date is before year 1.']
            );
        }
        $absoluteDay = $this->floorDiv($absoluteMinutes, 1440);
        $result = [
            'year' => $this->floorDiv($absoluteDay, $daysPerYear) + 1,
            'dayOfYear' => $this->mod($absoluteDay, $daysPerYear) + 1,
            'minuteOfDay' => $this->mod($absoluteMinutes, 1440),
        ];
        $this->assertDate(
            $definition,
            $result['year'],
            $result['dayOfYear'],
            $result['minuteOfDay']
        );
        return $result;
    }

    public function describe(array $definition, int $year, int $dayOfYear, int $minuteOfDay = 0): array
    {
        $this->assertDate($definition, $year, $dayOfYear, $minuteOfDay);
        $date = [
            'year' => $year,
            'dayOfYear' => $dayOfYear,
            'minuteOfDay' => $minuteOfDay,
            'time' => sprintf('%02d:%02d', intdiv($minuteOfDay, 60), $minuteOfDay % 60),
            'monthKey' => null,
            'monthName' => null,
            'day' => null,
            'specialDayKey' => null,
            'specialDayName' => null,
            'weekdayIndex' => null,
            'weekdayName' => null,
        ];
        foreach ($definition['intercalaryDays'] as $special) {
            if ((int) $special['dayOfYear'] === $dayOfYear) {
                $date['specialDayKey'] = $special['key'];
                $date['specialDayName'] = $special['name'];
                break;
            }
        }
        if ($date['specialDayKey'] === null) {
            foreach ($definition['months'] as $month) {
                $first = (int) $month['startDayOfYear'];
                $last = $first + (int) $month['length'] - 1;
                if ($dayOfYear >= $first && $dayOfYear <= $last) {
                    $date['monthKey'] = $month['key'];
                    $date['monthName'] = $month['name'];
                    $date['day'] = $dayOfYear - $first + 1;
                    $weekday = $this->weekdayIndex($definition, $year, $month, (int) $date['day']);
                    $date['weekdayIndex'] = $weekday;
                    $date['weekdayName'] = $definition['week']['names'][$weekday];
                    break;
                }
            }
        }
        $date['season'] = $this->season($definition, $dayOfYear);
        $date['holidays'] = array_values(array_filter(
            $definition['holidays'] ?? [],
            static function (array $holiday) use ($dayOfYear): bool {
                return (int) ($holiday['dayOfYear'] ?? 0) === $dayOfYear;
            }
        ));
        $date['mannslieb'] = $this->mannslieb($definition, $year, $dayOfYear);
        $date['formatted'] = $this->format($definition, $date);
        $date['formattedWithTime'] = strtr($definition['dateFormat']['time'], [
            '{date}' => $date['formatted'],
            '{time}' => $date['time'],
        ]);
        return $date;
    }

    public function compare(int $leftYear, int $leftDay, int $leftMinute, int $rightYear, int $rightDay, int $rightMinute): int
    {
        return [$leftYear, $leftDay, $leftMinute] <=> [$rightYear, $rightDay, $rightMinute];
    }

    private function weekdayIndex(array $definition, int $year, array $month, int $day): int
    {
        $anchor = $definition['week']['anchor'];
        $anchorMonth = null;
        foreach ($definition['months'] as $candidate) {
            if ($candidate['key'] === $anchor['monthKey']) {
                $anchorMonth = $candidate;
                break;
            }
        }
        if (!$anchorMonth) {
            throw new \LogicException('Calendar weekday anchor month is invalid.');
        }
        $current = ($year - (int) $anchor['year']) * (int) $definition['numberedDaysPerYear']
            + (int) $month['startNumberedIndex'] + $day - 1;
        $anchored = (int) $anchorMonth['startNumberedIndex'] + (int) $anchor['day'] - 1;
        return $this->mod(
            (int) $anchor['weekdayIndex'] + $current - $anchored,
            count($definition['week']['names'])
        );
    }

    private function season(array $definition, int $dayOfYear): ?array
    {
        $seasons = $definition['seasons'] ?? [];
        if (!$seasons) {
            return null;
        }
        usort($seasons, static function (array $a, array $b): int {
            return (int) $a['startDayOfYear'] <=> (int) $b['startDayOfYear'];
        });
        $selected = $seasons[count($seasons) - 1];
        foreach ($seasons as $season) {
            if ((int) $season['startDayOfYear'] <= $dayOfYear) {
                $selected = $season;
            }
        }
        return $selected;
    }

    private function mannslieb(array $definition, int $year, int $dayOfYear): ?array
    {
        $cycle = $definition['moonCycles']['mannslieb'] ?? null;
        if (!$cycle || ($cycle['type'] ?? '') !== 'cycle') {
            return null;
        }
        $anchor = $cycle['anchor'];
        $serial = ($year - (int) $anchor['year']) * (int) $definition['daysPerYear']
            + $dayOfYear - (int) $anchor['dayOfYear'] + (int) ($anchor['offset'] ?? 0);
        $offset = $this->mod($serial, (int) $cycle['periodDays']);
        $key = $cycle['phaseByOffset'][$offset];
        foreach ($cycle['phases'] as $phase) {
            if ($phase['key'] === $key) {
                return $phase + ['cycleOffset' => $offset];
            }
        }
        return null;
    }

    private function format(array $definition, array $date): string
    {
        $template = $date['specialDayName'] !== null
            ? $definition['dateFormat']['intercalary']
            : $definition['dateFormat']['regular'];
        return strtr($template, [
            '{day}' => (string) ($date['day'] ?? ''),
            '{month}' => (string) ($date['monthName'] ?? ''),
            '{special}' => (string) ($date['specialDayName'] ?? ''),
            '{year}' => (string) $date['year'],
            '{era}' => (string) $definition['era']['suffix'],
        ]);
    }

    private function floorDiv(int $value, int $divisor): int
    {
        $quotient = intdiv($value, $divisor);
        if ($value < 0 && $value % $divisor !== 0) {
            --$quotient;
        }
        return $quotient;
    }

    private function mod(int $value, int $divisor): int
    {
        return (($value % $divisor) + $divisor) % $divisor;
    }
}
