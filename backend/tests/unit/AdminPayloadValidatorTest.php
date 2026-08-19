<?php

use App\Services\Admin\AdminPayloadValidator;
use CodeIgniter\Test\CIUnitTestCase;

final class AdminPayloadValidatorTest extends CIUnitTestCase
{
    public function testValidatesNewUserAndNormalizesEmailAndRole(): void
    {
        $result = (new AdminPayloadValidator())->validateCreateUser([
            'username' => ' GameMaster ',
            'email' => ' GM@Example.COM ',
            'password' => 'long-safe-password',
            'role' => 'GM',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('GameMaster', $result['data']['username']);
        $this->assertSame('gm@example.com', $result['data']['email']);
        $this->assertSame('gm', $result['data']['role']);
    }

    public function testRejectsWeakAndManipulatedCreatePayload(): void
    {
        $result = (new AdminPayloadValidator())->validateCreateUser([
            'username' => 'x',
            'email' => 'wrong',
            'password' => 'short',
            'role' => 'root',
            'id' => 99,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertEqualsCanonicalizing(
            ['username', 'email', 'password', 'role', 'id'],
            array_keys($result['errors'])
        );
    }

    public function testRoleUpdateAcceptsOnlyKnownRoleField(): void
    {
        $validator = new AdminPayloadValidator();
        $valid = $validator->validateRole(['role' => 'ADMIN']);
        $invalid = $validator->validateRole(['role' => 'owner', 'user_id' => 1]);

        $this->assertTrue($valid['valid']);
        $this->assertSame('admin', $valid['data']['role']);
        $this->assertFalse($invalid['valid']);
        $this->assertArrayHasKey('user_id', $invalid['errors']);
    }
}
