<?php

use App\Services\Campaign\CampaignCreationPolicy;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CampaignCreationPolicyTest extends CIUnitTestCase
{
    /** @dataProvider authenticatedRoleProvider */
    public function testAuthenticatedApplicationRolesCanCreate(string $role): void
    {
        $this->assertTrue((new CampaignCreationPolicy())->allows([
            'user_id' => 7,
            'role' => $role,
            'anonymous' => false,
        ]));
    }

    public function authenticatedRoleProvider(): array
    {
        return [['user'], ['admin']];
    }

    public function testAnonymousAndUnknownRolesCannotCreate(): void
    {
        $policy = new CampaignCreationPolicy();
        $this->assertFalse($policy->allows([
            'user_id' => 7, 'role' => 'user', 'anonymous' => true,
        ]));
        $this->assertFalse($policy->allows([
            'user_id' => 7, 'role' => 'root', 'anonymous' => false,
        ]));
    }
}
