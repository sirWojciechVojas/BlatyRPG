<?php

use App\Services\Character\CharacterAccessPolicy;
use CodeIgniter\Test\CIUnitTestCase;

final class CharacterAccessPolicyTest extends CIUnitTestCase
{
    private $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new CharacterAccessPolicy();
    }

    public function testCampaignOwnerCanManageAssignedCharacter(): void
    {
        $campaign = $this->policy->campaign(
            ['user_id' => 7, 'role' => 'user', 'anonymous' => false],
            ['game_master_id' => 7],
            null
        );
        $access = $this->policy->character(
            $campaign,
            ['campaign_id' => 12, 'user_id' => null],
            7,
            12,
            false
        );

        $this->assertTrue($access['canView']);
        $this->assertTrue($access['canEdit']);
        $this->assertTrue($access['canDelete']);
    }

    public function testPlayerSeesOnlyOwnedOrClaimedCharacter(): void
    {
        $campaign = $this->policy->campaign(
            ['user_id' => 7, 'role' => 'user', 'anonymous' => false],
            ['game_master_id' => 3],
            ['user_id' => 7, 'role' => 'player', 'is_active' => 1]
        );

        $denied = $this->policy->character(
            $campaign,
            ['campaign_id' => 12, 'user_id' => 8],
            7,
            12,
            false
        );
        $claimed = $this->policy->character(
            $campaign,
            ['campaign_id' => 12, 'user_id' => 8],
            7,
            12,
            true
        );

        $this->assertFalse($denied['canView']);
        $this->assertTrue($claimed['canView']);
        $this->assertTrue($claimed['canEdit']);
        $this->assertFalse($claimed['canDelete']);
    }

    public function testCharacterFromAnotherCampaignIsAlwaysDenied(): void
    {
        $access = $this->policy->character(
            ['canAccess' => true, 'canManageAll' => true, 'isAdmin' => true],
            ['campaign_id' => 99, 'user_id' => 7],
            7,
            12,
            true
        );

        $this->assertSame(
            ['canView' => false, 'canEdit' => false, 'canDelete' => false],
            $access
        );
    }

    public function testLegacyUnassignedCharacterIsReadOnlyForNonAdminManager(): void
    {
        $access = $this->policy->character(
            ['canAccess' => true, 'canManageAll' => true, 'isAdmin' => false],
            ['campaign_id' => null, 'user_id' => null],
            7,
            12,
            false
        );

        $this->assertTrue($access['canView']);
        $this->assertFalse($access['canEdit']);
        $this->assertFalse($access['canDelete']);
    }
}
