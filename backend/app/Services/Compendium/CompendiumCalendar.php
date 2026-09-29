<?php

namespace App\Services\Compendium;

final class CompendiumCalendar
{
    /** Converts {eraId,year,monthId,day} into a stable signed day. */
    public static function ordinal(array $date, array $months, array $eras): array
    {
        $eraId = (int) ($date['eraId'] ?? 0);
        $year = (int) ($date['year'] ?? 0);
        $monthId = (int) ($date['monthId'] ?? 0);
        $day = (int) ($date['day'] ?? 0);
        if ($eraId < 1 || $year < 1 || $monthId < 1 || $day < 1) {
            return ['valid' => false, 'error' => 'Date requires an era, positive year, month and day.'];
        }
        $era = null;
        foreach ($eras as $candidate) if ((int) $candidate['id'] === $eraId) $era = $candidate;
        $month = null;
        $daysBefore = 0;
        $yearDays = 0;
        foreach ($months as $candidate) {
            $length = (int) ($candidate['days'] ?? 0);
            if ($length < 1) return ['valid' => false, 'error' => 'Calendar month length is invalid.'];
            if ((int) $candidate['id'] === $monthId) {
                $month = $candidate;
                $daysBefore = $yearDays;
            }
            $yearDays += $length;
        }
        if (!$era || !$month || $day > (int) $month['days'] || $yearDays < 1) {
            return ['valid' => false, 'error' => 'Date is outside the configured calendar.'];
        }
        $direction = (int) ($era['direction'] ?? 1) === -1 ? -1 : 1;
        $ordinal = (int) $era['epoch_ordinal']
            + $direction * (($year - 1) * $yearDays + $daysBefore + $day - 1);
        return ['valid' => true, 'ordinal' => $ordinal];
    }
}
