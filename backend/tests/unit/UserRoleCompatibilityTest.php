<?php

use App\Services\Auth\AuthUserPresenter;
use App\Services\Auth\UserRole;
use CodeIgniter\Test\CIUnitTestCase;

final class UserRoleCompatibilityTest extends CIUnitTestCase
{
    public function testLegacyRolesArePresentedAsRegularAccounts(): void
    {
        $presented = (new AuthUserPresenter())->present([
            'id' => 1,
            'username' => 'legacy',
            'email' => 'legacy@example.test',
            'role' => 'gm',
        ]);

        $this->assertSame(UserRole::USER, $presented['role']);
        $this->assertTrue(UserRole::isSupported('user'));
        $this->assertTrue(UserRole::isSupported('player'));
        $this->assertSame(['user', 'admin'], UserRole::all());
    }
}
