<?php

use App\Services\Campaign\CampaignInvitationValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CampaignInvitationValidatorTest extends CIUnitTestCase
{
    public function testAcceptsLocalGmRoleIndependentlyOfGlobalRole(): void
    {
        $result = (new CampaignInvitationValidator())->validate([
            'identifier' => 'player@example.com',
            'role' => 'gm',
            'message' => 'Please run this world with me.',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('gm', $result['data']['role']);
    }

    public function testRejectsUnknownRoleAndFields(): void
    {
        $result = (new CampaignInvitationValidator())->validate([
            'userId' => 4,
            'role' => 'admin',
            'campaignId' => 999,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('role', $result['errors']);
        $this->assertArrayHasKey('payload', $result['errors']);
    }
}
