<?php

use App\Services\Auth\AuthUserPresenter;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class AuthUserPresenterTest extends CIUnitTestCase
{
    public function testPresentsSessionUserWithoutCredentialMaterial(): void
    {
        $result = (new AuthUserPresenter())->present([
            'id' => '7',
            'username' => 'GameMaster',
            'email' => 'gm@example.test',
            'role' => 'ADMIN',
            'avatar_url' => null,
            'password_hash' => 'must-never-leak',
        ]);

        $this->assertSame(7, $result['id']);
        $this->assertSame('GameMaster', $result['login']);
        $this->assertSame('admin', $result['role']);
        $this->assertArrayNotHasKey('password_hash', $result);
    }
}
