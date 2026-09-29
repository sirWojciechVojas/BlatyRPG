<?php

namespace Tests\Unit;

use App\Services\Calendar\CalendarDefinitionRegistry;
use App\Services\Calendar\CalendarEventValidator;
use App\Services\Calendar\CalendarException;
use CodeIgniter\Test\CIUnitTestCase;

final class CalendarEventValidatorTest extends CIUnitTestCase
{
    private $definition;
    private $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->definition = (new CalendarDefinitionRegistry())->get('wfrp-imperial');
        $this->validator = new CalendarEventValidator();
    }

    public function testAcceptsNeutralCalendarDateAndVisibilityParticipants(): void
    {
        $event = $this->validator->validate($this->definition, [
            'title' => 'Eskorta do Altdorfu',
            'description' => 'Wyruszyć przed świtem.',
            'type' => 'travel',
            'start' => ['year' => 2522, 'dayOfYear' => 66, 'minute' => 420],
            'end' => ['year' => 2522, 'dayOfYear' => 68, 'minute' => 600],
            'color' => '#74552f',
            'visibility' => 'participants',
            'participantUserIds' => [4, 4, 7],
            'allDay' => false,
            'repeatYearly' => false,
            'expectedRevision' => 3,
        ]);

        $this->assertSame(66, $event['start_day_of_year']);
        $this->assertSame(68, $event['end_day_of_year']);
        $this->assertSame([4, 7], $event['participant_user_ids']);
        $this->assertSame(420, $event['start_minute']);
    }

    public function testRejectsEndEarlierThanStart(): void
    {
        $this->expectException(CalendarException::class);
        $this->validator->validate($this->definition, [
            'title' => 'Niepoprawny zakres',
            'type' => 'story',
            'start' => ['year' => 2522, 'dayOfYear' => 201, 'minute' => 600],
            'end' => ['year' => 2522, 'dayOfYear' => 200, 'minute' => 600],
            'visibility' => 'all',
            'allDay' => false,
            'repeatYearly' => false,
        ]);
    }

    public function testRejectsAnOutOfRangeImperialDay(): void
    {
        $this->expectException(CalendarException::class);
        $this->validator->validate($this->definition, [
            'title' => 'Poza rokiem',
            'type' => 'quest',
            'start' => ['year' => 2522, 'dayOfYear' => 401],
            'visibility' => 'all',
            'allDay' => true,
            'repeatYearly' => false,
        ]);
    }
}
