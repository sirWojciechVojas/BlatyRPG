<?php

namespace Tests\Unit;

use App\Services\Calendar\CalendarAccessPolicy;
use App\Services\Calendar\CalendarException;
use App\Services\Calendar\CalendarRevisionGuard;
use CodeIgniter\Test\CIUnitTestCase;

final class CalendarAccessAndRevisionTest extends CIUnitTestCase
{
    public function testPlayerCannotChangeWorldTime(): void
    {
        $this->expectException(CalendarException::class);
        (new CalendarAccessPolicy())->requireManage([
            'auth' => ['user_id' => 7],
            'isAdmin' => false,
            'isGameMaster' => false,
            'isOwner' => false,
        ]);
    }

    public function testPrivateEventsAreFilteredToSelectedParticipants(): void
    {
        $policy = new CalendarAccessPolicy();
        $event = ['visibility' => 'participants'];
        $player = [
            'auth' => ['user_id' => 7],
            'isAdmin' => false,
            'isGameMaster' => false,
            'isOwner' => false,
        ];
        $this->assertTrue($policy->canViewEvent($event, $player, [7, 9]));
        $this->assertFalse($policy->canViewEvent($event, $player, [9]));
        $this->assertFalse($policy->canViewEvent(['visibility' => 'gm'], $player));
    }

    public function testRevisionConflictIsDetected(): void
    {
        $this->expectException(CalendarException::class);
        $this->expectExceptionCode(0);
        (new CalendarRevisionGuard())->assertExpected(4, 5);
    }

    public function testMatchingRevisionIsAccepted(): void
    {
        (new CalendarRevisionGuard())->assertExpected(5, 5);
        $this->addToAssertionCount(1);
    }
}
