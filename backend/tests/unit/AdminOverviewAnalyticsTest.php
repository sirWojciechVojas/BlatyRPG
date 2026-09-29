<?php

use App\Services\Admin\AdminOverviewAnalytics;
use CodeIgniter\Test\CIUnitTestCase;

final class AdminOverviewAnalyticsTest extends CIUnitTestCase
{
    public function testBuildsDashboardFromRealDomainRows(): void
    {
        $result = (new AdminOverviewAnalytics())->build(
            [
                ['role' => 'user', 'username' => 'Ada', 'created_at' => date('Y-m-d H:i:s')],
                ['role' => 'admin', 'username' => 'Root', 'created_at' => date('Y-m-d H:i:s')],
            ],
            [
                [
                    'name' => 'Enemy Within',
                    'system_type' => 'wfrp2ed',
                    'status' => 'active',
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ],
            ],
            [
                ['role' => 'gm'],
                ['role' => 'player'],
            ]
        );

        $this->assertSame(2, $result['metrics']['users']);
        $this->assertSame(1, $result['metrics']['activeCampaigns']);
        $this->assertSame(2, $result['metrics']['memberships']);
        $this->assertSame(
            [['key' => 'user', 'value' => 1], ['key' => 'admin', 'value' => 1]],
            $result['analytics']['accountRoles']
        );
        $this->assertCount(8, $result['analytics']['growth']);
        $this->assertNotEmpty($result['activity']);
    }
}
