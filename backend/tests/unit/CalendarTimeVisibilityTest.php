<?php

namespace Tests\Unit;

use App\Services\Calendar\CalendarDefinitionRegistry;
use App\Services\Calendar\CalendarPresenter;
use CodeIgniter\Test\CIUnitTestCase;

final class CalendarTimeVisibilityTest extends CIUnitTestCase
{
    public function testPresenterExposesCampaignTimeVisibility(): void
    {
        $definition = (new CalendarDefinitionRegistry())->get('wfrp-imperial');
        $row = [
            'campaign_id' => 7,
            'calendar_key' => 'wfrp-imperial',
            'year' => 2522,
            'day_of_year' => 2,
            'minute_of_day' => 480,
            'show_time' => 0,
            'is_running' => 0,
            'revision' => 3,
            'updated_by_user_id' => 4,
            'updated_at' => '2026-09-21 08:00:00',
        ];

        $hidden = (new CalendarPresenter())->state($definition, $row);
        $this->assertFalse($hidden['showTime']);
        $this->assertSame('08:00', $hidden['date']['time']);

        $row['show_time'] = 1;
        $visible = (new CalendarPresenter())->state($definition, $row);
        $this->assertTrue($visible['showTime']);
    }
}
