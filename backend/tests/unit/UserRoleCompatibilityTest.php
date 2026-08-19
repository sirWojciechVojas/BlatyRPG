<?php

use App\Services\Auth\AuthUserPresenter;
use App\Services\Auth\UserRole;
use CodeIgniter\Test\CIUnitTestCase;

final class UserRoleCompatibilityTest extends CIUnitTestCase
{
    public function testLegacyUserIsPresentedAsCanonicalPlayer(): void
    {
        $presented = (new AuthUserPresenter())->present([
            'id' => 1,
            'username' => 'legacy',
            'email' => 'legacy@example.test',
            'role' => 'user',
        ]);

        $this->assertSame(UserRole::PLAYER, $presented['role']);
        $this->assertTrue(UserRole::isSupported('user'));
        $this->assertSame(['player', 'gm', 'admin'], UserRole::all());
    }
}
