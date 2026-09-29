<?php

use App\Services\Compendium\CompendiumCalendar;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CompendiumCalendarTest extends CIUnitTestCase
{
    private $months = [
        ['id' => 10, 'name' => 'Dawn', 'days' => 30],
        ['id' => 20, 'name' => 'Dusk', 'days' => 20],
    ];

    private $eras = [
        ['id' => 1, 'epoch_ordinal' => 1000, 'direction' => 1],
        ['id' => 2, 'epoch_ordinal' => 999, 'direction' => -1],
    ];

    public function testConvertsMonthAndYearBoundariesToStableOrdinals(): void
    {
        $this->assertSame(1000, CompendiumCalendar::ordinal([
            'eraId' => 1, 'year' => 1, 'monthId' => 10, 'day' => 1,
        ], $this->months, $this->eras)['ordinal']);
        $this->assertSame(1030, CompendiumCalendar::ordinal([
            'eraId' => 1, 'year' => 1, 'monthId' => 20, 'day' => 1,
        ], $this->months, $this->eras)['ordinal']);
        $this->assertSame(1050, CompendiumCalendar::ordinal([
            'eraId' => 1, 'year' => 2, 'monthId' => 10, 'day' => 1,
        ], $this->months, $this->eras)['ordinal']);
    }

    public function testSupportsBackwardCountingEras(): void
    {
        $this->assertSame(949, CompendiumCalendar::ordinal([
            'eraId' => 2, 'year' => 2, 'monthId' => 10, 'day' => 1,
        ], $this->months, $this->eras)['ordinal']);
    }

    public function testRejectsUnknownAndOutOfRangeDates(): void
    {
        $this->assertFalse(CompendiumCalendar::ordinal([
            'eraId' => 1, 'year' => 1, 'monthId' => 20, 'day' => 21,
        ], $this->months, $this->eras)['valid']);
        $this->assertFalse(CompendiumCalendar::ordinal([
            'eraId' => 99, 'year' => 1, 'monthId' => 10, 'day' => 1,
        ], $this->months, $this->eras)['valid']);
    }
}
