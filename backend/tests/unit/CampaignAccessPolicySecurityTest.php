<?php

use App\Services\Campaign\CampaignAccessPolicy;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CampaignAccessPolicySecurityTest extends CIUnitTestCase
{
    public function testStringFalseDoesNotGrantSceneManagement(): void
    {
        $result = (new CampaignAccessPolicy())->evaluate(
            ['user_id' => 8, 'role' => 'user', 'anonymous' => false],
            ['game_master_id' => 7],
            [
                'user_id' => 8,
                'role' => 'player',
                'is_active' => 1,
                'permissions_json' => [
                    'manage_scenes' => 'false',
                    'view_hidden_scenes' => 'false',
                ],
            ]
        );

        $this->assertTrue($result['canAccess']);
        $this->assertFalse($result['canManage']);
        $this->assertFalse($result['canViewHidden']);
    }

    public function testUnknownCampaignRoleFailsClosed(): void
    {
        $result = (new CampaignAccessPolicy())->evaluate(
            ['user_id' => 8, 'role' => 'user', 'anonymous' => false],
            ['game_master_id' => 7],
            ['user_id' => 8, 'role' => 'superuser', 'is_active' => 1]
        );

        $this->assertFalse($result['canAccess']);
        $this->assertFalse($result['canManage']);
        $this->assertFalse($result['canViewHidden']);
    }

    public function testScenePermissionDoesNotGrantCampaignAdministration(): void
    {
        $result = (new CampaignAccessPolicy())->evaluate(
            ['user_id' => 8, 'role' => 'player', 'anonymous' => false],
            ['game_master_id' => 7],
            [
                'user_id' => 8,
                'role' => 'player',
                'is_active' => 1,
                'permissions_json' => ['manage_scenes' => true],
            ]
        );

        $this->assertTrue($result['canAccess']);
        $this->assertTrue($result['canManageScenes']);
        $this->assertFalse($result['canManage']);
    }
}
