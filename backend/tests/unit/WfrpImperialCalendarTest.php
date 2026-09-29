<?php

namespace Tests\Unit;

use App\Services\Calendar\CalendarDateEngine;
use App\Services\Calendar\CalendarDefinitionRegistry;
use CodeIgniter\Test\CIUnitTestCase;

final class WfrpImperialCalendarTest extends CIUnitTestCase
{
    private $definition;
    private $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->definition = (new CalendarDefinitionRegistry())->get('wfrp-imperial');
        $this->engine = new CalendarDateEngine();
    }

    public function testYearContainsExactlyFourHundredDays(): void
    {
        $this->assertSame(400, $this->definition['daysPerYear']);
        $this->assertSame(394, $this->definition['numberedDaysPerYear']);
        $this->assertSame(394, array_sum(array_column($this->definition['months'], 'length')));
        $this->assertFalse($this->definition['defaultState']['showTime']);
    }

    public function testMonthLengthsMatchImperialCalendar(): void
    {
        $this->assertSame(
            [32, 33, 33, 33, 33, 33, 32, 33, 33, 33, 33, 33],
            array_column($this->definition['months'], 'length')
        );
    }

    public function testAllIntercalaryDaysOccupyTheirPdfPositions(): void
    {
        $this->assertSame(
            [
                'noc-wiedzm' => 1,
                'rozkwitanie' => 67,
                'dzien-slonca' => 167,
                'noc-tajemnicy' => 201,
                'przekwitanie' => 267,
                'uspienie' => 367,
            ],
            array_column($this->definition['intercalaryDays'], 'dayOfYear', 'key')
        );
        foreach ($this->definition['intercalaryDays'] as $special) {
            $date = $this->engine->describe($this->definition, 2522, $special['dayOfYear']);
            $this->assertNull($date['monthKey']);
            $this->assertNull($date['weekdayName']);
        }
    }

    public function testSeasonBeginningsMatchReferenceCalendar(): void
    {
        $expected = ['wiosna' => 18, 'lato' => 118, 'jesien' => 218, 'zima' => 318];
        $this->assertSame($expected, array_column($this->definition['seasons'], 'startDayOfYear', 'key'));
        foreach ($expected as $key => $dayOfYear) {
            $this->assertSame(
                $key,
                $this->engine->describe($this->definition, 2522, $dayOfYear)['season']['key']
            );
        }
    }

    public function testWeekdayLayoutFor2522MatchesPdfExamples(): void
    {
        $examples = [
            ['powiedzime', 1, 'Dzień Pracy'],
            ['powiedzime', 32, 'Dzień Świąteczny'],
            ['zmiana-roku', 33, 'Dzień Pracy'],
            ['czas-orki', 1, 'Dzień Poboru'],
            ['czas-sigmara', 1, 'Dzień Targowy'],
            ['czas-lata', 1, 'Dzień Wypieków'],
            ['przed-tajemnica', 1, 'Dzień Podatków'],
            ['po-tajemnicy', 1, 'Dzień Królewski'],
            ['czas-zbiorow', 1, 'Dzień Królewski'],
            ['czas-warzenia', 1, 'Dzień Początku'],
            ['czas-mrozow', 1, 'Dzień Świąteczny'],
            ['czas-ulryka', 1, 'Dzień Pracy'],
            ['przedwiedzime', 1, 'Dzień Poboru'],
        ];
        foreach ($examples as [$month, $day, $weekday]) {
            $date = $this->engine->describe(
                $this->definition,
                2522,
                $this->engine->dayOfYear($this->definition, $month, $day)
            );
            $this->assertSame($weekday, $date['weekdayName'], "{$month} {$day}");
        }
    }

    public function testMovesBothWaysAcrossEveryMonthSpecialDayAndYearBoundary(): void
    {
        for ($day = 1; $day < $this->definition['daysPerYear']; ++$day) {
            $forward = $this->engine->advance($this->definition, [
                'year' => 2522, 'dayOfYear' => $day, 'minuteOfDay' => 480,
            ], 1, 'days');
            $back = $this->engine->advance($this->definition, [
                'year' => $forward['year'],
                'dayOfYear' => $forward['dayOfYear'],
                'minuteOfDay' => $forward['minuteOfDay'],
            ], -1, 'days');
            $this->assertSame([2522, $day, 480], [$back['year'], $back['dayOfYear'], $back['minuteOfDay']]);
        }
        $nextYear = $this->engine->advance($this->definition, [
            'year' => 2522, 'dayOfYear' => 400, 'minuteOfDay' => 1439,
        ], 1, 'minutes');
        $this->assertSame([2523, 1, 0], [$nextYear['year'], $nextYear['dayOfYear'], $nextYear['minuteOfDay']]);
        $previous = $this->engine->advance($this->definition, $nextYear, -1, 'minutes');
        $this->assertSame([2522, 400, 1439], [$previous['year'], $previous['dayOfYear'], $previous['minuteOfDay']]);
    }

    public function testFormatsRegularAndIntercalaryDates(): void
    {
        $regular = $this->engine->describe($this->definition, 2522, 2, 480);
        $special = $this->engine->describe($this->definition, 2522, 201, 0);
        $this->assertSame('1 Powiedźmie, 2522 KI', $regular['formatted']);
        $this->assertSame('1 Powiedźmie, 2522 KI, 08:00', $regular['formattedWithTime']);
        $this->assertSame('Noc Tajemnicy, 2522 KI', $special['formatted']);
    }

    public function testMannsliebCycleUsesAlmanacNewAndFullMoonAnchors(): void
    {
        $this->assertSame('now', $this->engine->describe($this->definition, 2522, 1)['mannslieb']['key']);
        $this->assertSame('pelnia', $this->engine->describe($this->definition, 2522, 14)['mannslieb']['key']);
        $this->assertSame('now', $this->engine->describe($this->definition, 2522, 26)['mannslieb']['key']);
        $this->assertSame('pelnia', $this->engine->describe($this->definition, 2522, 39)['mannslieb']['key']);
    }
}
