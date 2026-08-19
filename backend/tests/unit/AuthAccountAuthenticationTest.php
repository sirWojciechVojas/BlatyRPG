<?php

use App\Models\UserModel;
use App\Services\Auth\AuthAccountService;
use App\Services\Auth\AuthException;
use App\Services\Auth\AuthSessionService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

final class AuthenticationUserStoreStub extends UserModel
{
    private $row;

    public function __construct(?array $row)
    {
        $this->row = $row;
    }

    public function withDeleted(bool $val = true)
    {
        return $this;
    }

    public function groupStart()
    {
        return $this;
    }

    public function where($key = null, $value = null, ?bool $escape = null)
    {
        return $this;
    }

    public function orWhere($key, $value = null, ?bool $escape = null)
    {
        return $this;
    }

    public function groupEnd()
    {
        return $this;
    }

    public function first()
    {
        return $this->row;
    }
}

/** @internal */
final class AuthAccountAuthenticationTest extends CIUnitTestCase
{
    public function testValidCredentialsReturnCurrentActiveUser(): void
    {
        $user = $this->user();
        $result = $this->service($user)->authenticate(
            'player@example.test',
            'CorrectHorseBattery9!'
        );

        $this->assertSame(7, $result['id']);
        $this->assertSame('player', $result['role']);
    }

    /**
     * @dataProvider rejectedAccountProvider
     */
    public function testRejectedAccountsUseGenericInvalidCredentials(
        ?array $user,
        string $password
    ): void {
        try {
            $this->service($user)->authenticate('player@example.test', $password);
            $this->fail('Rejected credentials must not authenticate.');
        } catch (AuthException $exception) {
            $this->assertSame(401, $exception->status());
            $this->assertSame('invalid_credentials', $exception->errorCode());
            $this->assertSame('Invalid login or password.', $exception->getMessage());
        }
    }

    public function rejectedAccountProvider(): array
    {
        $deleted = $this->user();
        $deleted['deleted_at'] = '2026-08-19 12:00:00';
        $unsupported = $this->user();
        $unsupported['role'] = 'superuser';

        return [
            'unknown account' => [null, 'CorrectHorseBattery9!'],
            'wrong password' => [$this->user(), 'WrongPassword123!'],
            'deleted account' => [$deleted, 'CorrectHorseBattery9!'],
            'unsupported role' => [$unsupported, 'CorrectHorseBattery9!'],
        ];
    }

    private function user(): array
    {
        return [
            'id' => 7,
            'username' => 'player',
            'email' => 'player@example.test',
            'password_hash' => password_hash(
                'CorrectHorseBattery9!',
                PASSWORD_DEFAULT
            ),
            'role' => 'player',
            'deleted_at' => null,
        ];
    }

    private function service(?array $user): AuthAccountService
    {
        $db = $this->getMockBuilder(BaseConnection::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $sessions = (new ReflectionClass(AuthSessionService::class))
            ->newInstanceWithoutConstructor();

        return new AuthAccountService(
            $db,
            new AuthenticationUserStoreStub($user),
            $sessions
        );
    }
}
