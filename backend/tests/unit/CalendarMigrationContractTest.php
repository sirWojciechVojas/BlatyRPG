<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class CalendarMigrationContractTest extends CIUnitTestCase
{
    public function testMigrationDefinesNeutralDatesIndexesAndForeignKeys(): void
    {
        $source = file_get_contents(APPPATH . 'Database/Migrations/2026-09-20-120000_CreateCampaignCalendars.php');
        $this->assertStringContainsString("'day_of_year'", $source);
        $this->assertStringNotContainsString("'start_date' => ['type' => 'DATE'", $source);
        $this->assertStringContainsString("['campaign_id', 'start_year', 'start_day_of_year', 'start_minute']", $source);
        $this->assertStringContainsString("['campaign_id', 'visibility', 'start_year']", $source);
        $this->assertStringContainsString("addForeignKey('campaign_id', 'campaigns'", $source);
        $this->assertStringContainsString("addForeignKey('user_id', 'users'", $source);
    }

    public function testTimeVisibilityMigrationIsReversible(): void
    {
        $source = file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-21-080000_AddCalendarTimeVisibility.php'
        );
        $this->assertStringContainsString("'show_time'", $source);
        $this->assertStringContainsString("'default' => 1", $source);
        $this->assertStringContainsString("dropColumn('campaign_calendars', 'show_time')", $source);
    }

    public function testTimeDisplayIsDisabledForExistingAndNewCalendars(): void
    {
        $source = file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-21-090000_DisableCalendarTimeDisplay.php'
        );
        $this->assertStringContainsString("'default' => 0", $source);
        $this->assertStringContainsString("update(['show_time' => 0])", $source);
        $this->assertStringContainsString("'default' => 1", $source);
        $this->assertStringContainsString("update(['show_time' => 1])", $source);
    }
}
