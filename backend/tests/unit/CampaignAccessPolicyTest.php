<?php

use App\Services\Campaign\CampaignAccessPolicy;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CampaignAccessPolicyTest extends CIUnitTestCase
{
    public function testCampaignGameMasterCanManageScenes(): void
    {
        $result = (new CampaignAccessPolicy())->evaluate(
            ['user_id' => 7, 'role' => 'gm', 'anonymous' => false],
            ['game_master_id' => 7],
            null
        );

        $this->assertTrue($result['canAccess']);
        $this->assertTrue($result['canManage']);
        $this->assertTrue($result['canViewHidden']);
    }

    public function testPlayerPermissionCanGrantHiddenSceneAccess(): void
    {
        $result = (new CampaignAccessPolicy())->evaluate(
            ['user_id' => 8, 'role' => 'user', 'anonymous' => false],
            ['game_master_id' => 7],
            [
                'user_id' => 8,
                'role' => 'player',
                'is_active' => 1,
                'permissions_json' => ['view_hidden_scenes' => true],
            ]
        );

        $this->assertTrue($result['canAccess']);
        $this->assertFalse($result['canManage']);
        $this->assertTrue($result['canViewHidden']);
    }

    public function testInactiveMembershipIsDenied(): void
    {
        $result = (new CampaignAccessPolicy())->evaluate(
            ['user_id' => 8, 'role' => 'user', 'anonymous' => false],
            ['game_master_id' => 7],
            ['user_id' => 8, 'role' => 'player', 'is_active' => 0]
        );

        $this->assertFalse($result['canAccess']);
    }
}
