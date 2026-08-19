<?php

use App\Services\Auth\AuthSessionPolicy;
use CodeIgniter\Test\CIUnitTestCase;

final class AuthSessionPolicyTest extends CIUnitTestCase
{
    public function testUsesCurrentDatabaseRoleInsteadOfStaleJwtRole(): void
    {
        $result = (new AuthSessionPolicy())->resolve(
            ['user_id' => 5, 'role' => 'player', 'expires_at' => 2000],
            ['id' => 9, 'user_id' => 5, 'expires_at' => '1970-01-01 00:33:20', 'revoked_at' => null],
            ['id' => 5, 'role' => 'gm', 'deleted_at' => null],
            1000
        );

        $this->assertTrue($result['valid']);
        $this->assertSame('gm', $result['role']);
    }

    /**
     * @dataProvider invalidPrincipalProvider
     */
    public function testRejectsRevokedExpiredDeletedOrMismatchedPrincipal(
        array $decoded,
        array $session,
        array $user
    ): void {
        $result = (new AuthSessionPolicy())->resolve($decoded, $session, $user, 1000);

        $this->assertFalse($result['valid']);
    }

    public function invalidPrincipalProvider(): array
    {
        $decoded = ['user_id' => 5, 'expires_at' => 2000];
        $session = [
            'id' => 9,
            'user_id' => 5,
            'expires_at' => '1970-01-01 00:33:20',
            'revoked_at' => null,
        ];
        $user = ['id' => 5, 'role' => 'player', 'deleted_at' => null];

        return [
            'revoked' => [$decoded, array_replace($session, [
                'revoked_at' => '1970-01-01 00:16:40',
            ]), $user],
            'expired session' => [$decoded, array_replace($session, [
                'expires_at' => '1970-01-01 00:15:00',
            ]), $user],
            'deleted user' => [$decoded, $session, array_replace($user, [
                'deleted_at' => '1970-01-01 00:15:00',
            ])],
            'mismatched user' => [$decoded, $session, array_replace($user, ['id' => 6])],
        ];
    }
}
