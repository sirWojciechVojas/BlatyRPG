<?php

use App\Services\Campaign\CampaignRoleContext;
use CodeIgniter\Test\CIUnitTestCase;

final class CampaignRoleContextTest extends CIUnitTestCase
{
    public function testGmCanBeAPlayerAtAnotherGameMastersCampaign(): void
    {
        $auth = ['user_id' => 8, 'role' => 'user'];
        $own = CampaignRoleContext::resolve(
            $auth,
            ['game_master_id' => 8],
            ['user_id' => 8, 'role' => 'gm', 'is_active' => 1]
        );
        $other = CampaignRoleContext::resolve(
            $auth,
            ['game_master_id' => 7],
            ['user_id' => 8, 'role' => 'player', 'is_active' => 1]
        );

        $this->assertSame('gm', $own['campaignRole']);
        $this->assertTrue($own['isGameMaster']);
        $this->assertSame('player', $other['campaignRole']);
        $this->assertTrue($other['isPlayer']);
        $this->assertFalse($other['isGameMaster']);
    }

    public function testAdminKeepsGlobalPrivilegeWhileUsingCampaignRole(): void
    {
        $auth = ['user_id' => 9, 'role' => 'admin'];
        $asGm = CampaignRoleContext::resolve(
            $auth,
            ['game_master_id' => 9],
            ['user_id' => 9, 'role' => 'gm', 'is_active' => 1]
        );
        $asPlayer = CampaignRoleContext::resolve(
            $auth,
            ['game_master_id' => 7],
            ['user_id' => 9, 'role' => 'player', 'is_active' => 1]
        );

        $this->assertTrue($asGm['isAdmin']);
        $this->assertSame('gm', $asGm['accessRole']);
        $this->assertTrue($asPlayer['isAdmin']);
        $this->assertSame('player', $asPlayer['accessRole']);
        $this->assertSame('admin', $asPlayer['globalRole']);
    }

    public function testAdminWithoutMembershipUsesAdministrativeAccessRole(): void
    {
        $context = CampaignRoleContext::resolve(
            ['user_id' => 9, 'role' => 'admin'],
            ['game_master_id' => 7],
            null
        );

        $this->assertNull($context['campaignRole']);
        $this->assertSame('admin', $context['accessRole']);
    }
}
