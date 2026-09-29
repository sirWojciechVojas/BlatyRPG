<?php

namespace App\Services\Calendar;

final class CalendarRevisionGuard
{
    public function assertExpected(int $expected, int $actual): void
    {
        if ($expected < 1) {
            throw new CalendarException(
                'validation_failed',
                'A valid calendar revision is required.',
                422,
                ['expectedRevision' => 'A positive revision is required.']
            );
        }
        if ($expected !== $actual) {
            throw new CalendarException(
                'calendar_revision_conflict',
                'Calendar changed in another session. Refresh and try again.',
                409,
                ['expectedRevision' => $expected, 'actualRevision' => $actual]
            );
        }
    }
}
