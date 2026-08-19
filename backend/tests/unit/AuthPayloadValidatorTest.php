<?php

use App\Services\Auth\AuthPayloadValidator;
use CodeIgniter\Test\CIUnitTestCase;

final class AuthPayloadValidatorTest extends CIUnitTestCase
{
    public function testRegistrationNormalizesIdentityAndAcceptsStrongPassword(): void
    {
        $result = (new AuthPayloadValidator())->register([
            'username' => ' Player.One ',
            'email' => ' PLAYER@Example.COM ',
            'password' => 'LongSafePassword123',
            'confirmPassword' => 'LongSafePassword123',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('Player.One', $result['data']['username']);
        $this->assertSame('player@example.com', $result['data']['email']);
        $this->assertArrayNotHasKey('role', $result['data']);
    }

    public function testRegistrationRejectsWeakPasswordAndIdentityManipulation(): void
    {
        $result = (new AuthPayloadValidator())->register([
            'username' => 'xy',
            'email' => 'invalid',
            'password' => 'short',
            'confirm_password' => 'different',
            'role' => 'admin',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('username', $result['errors']);
        $this->assertArrayHasKey('email', $result['errors']);
        $this->assertArrayHasKey('password', $result['errors']);
        $this->assertArrayHasKey('confirmPassword', $result['errors']);
        $this->assertArrayHasKey('role', $result['errors']);
    }

    public function testLoginRejectsOversizedOrUnexpectedPayload(): void
    {
        $result = (new AuthPayloadValidator())->login([
            'login' => str_repeat('a', 256),
            'password' => str_repeat('x', 201),
            'role' => 'admin',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('credentials', $result['errors']);
        $this->assertArrayHasKey('role', $result['errors']);
    }

    public function testLoginUsesFirstNonEmptyAliasAndRejectsConflictingAliases(): void
    {
        $validator = new AuthPayloadValidator();
        $valid = $validator->login([
            'email' => '',
            'login' => 'player@example.test',
            'password' => 'CurrentPassword123',
        ]);
        $conflicting = $validator->login([
            'email' => 'one@example.test',
            'username' => 'another-user',
            'password' => 'CurrentPassword123',
        ]);

        $this->assertTrue($valid['valid']);
        $this->assertSame('player@example.test', $valid['data']['identifier']);
        $this->assertFalse($conflicting['valid']);
        $this->assertArrayHasKey('credentials', $conflicting['errors']);
    }

    public function testProfileRejectsUnsafeAvatarAndUnknownFields(): void
    {
        $result = (new AuthPayloadValidator())->profile([
            'avatarUrl' => 'javascript:alert(1)',
            'id' => 9,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('avatarUrl', $result['errors']);
        $this->assertArrayHasKey('id', $result['errors']);
    }

    /**
     * @dataProvider unsafeAvatarProvider
     */
    public function testProfileRejectsProtocolRelativeTraversalAndBackslashAvatars(string $avatar): void
    {
        $result = (new AuthPayloadValidator())->profile(['avatarUrl' => $avatar]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('avatarUrl', $result['errors']);
    }

    public function unsafeAvatarProvider(): array
    {
        return [
            'protocol relative' => ['//evil.example/avatar.png'],
            'path traversal' => ['/avatars/../secret.png'],
            'backslash' => ['/avatars\\unsafe.png'],
        ];
    }

    public function testPasswordChangeRequiresCurrentDifferentStrongPassword(): void
    {
        $validator = new AuthPayloadValidator();
        $invalid = $validator->changePassword([
            'currentPassword' => 'SamePassword123',
            'newPassword' => 'SamePassword123',
            'confirmPassword' => 'SamePassword123',
        ]);
        $valid = $validator->changePassword([
            'currentPassword' => 'OldPassword123',
            'newPassword' => 'NewPassword456',
            'confirmPassword' => 'NewPassword456',
        ]);

        $this->assertFalse($invalid['valid']);
        $this->assertArrayHasKey('newPassword', $invalid['errors']);
        $this->assertTrue($valid['valid']);
    }

    public function testResetConfirmationRequiresFixedLengthHexToken(): void
    {
        $result = (new AuthPayloadValidator())->resetConfirm([
            'token' => '../unsafe',
            'password' => 'StrongPassword123',
            'confirmPassword' => 'StrongPassword123',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('token', $result['errors']);
    }
}
