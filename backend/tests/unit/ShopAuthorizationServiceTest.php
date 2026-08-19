<?php

use App\Services\Shop\ShopAuthorizationService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ShopAuthorizationServiceTest extends CIUnitTestCase
{
    public function testAssertGmWithoutCampaignDoesNotGrantGlobalGmAccess(): void
    {
        $service = new ShopAuthorizationService();

        $result = $service->assertGm(['role' => 'gm']);

        $this->assertFalse($result['ok']);
    }

    public function testAssertOwnerAccessFailsWithoutUserContext(): void
    {
        $service = new ShopAuthorizationService();

        $result = $service->assertOwnerAccess(['role' => 'user', 'user_id' => null], 1, 'BG1');

        $this->assertFalse($result['ok']);
        $this->assertSame('forbidden', $result['code']);
        $this->assertSame(403, $result['status']);
    }

    public function testAssertOwnerAccessPassesForExplicitDevelopmentGm(): void
    {
        $service = new ShopAuthorizationService();

        $result = $service->assertOwnerAccess([
            'role' => 'gm',
            'user_id' => null,
            'development_access' => true,
        ], 1, 'BG9');

        $this->assertTrue($result['ok']);
    }

    public function testDevelopmentPlayerCanOnlyUseSelectedOwner(): void
    {
        $service = new ShopAuthorizationService();
        $auth = [
            'role' => 'user',
            'development_access' => true,
            'selected_owner_codes' => ['BG2'],
        ];

        $this->assertTrue($service->assertOwnerAccess($auth, 1, 'BG2')['ok']);
        $this->assertFalse($service->assertOwnerAccess($auth, 1, 'BG1')['ok']);
    }
}
