<?php

use App\Models\UserModel;
use App\Services\Auth\AuthAccountService;
use App\Services\Auth\AuthException;
use App\Services\Auth\OAuthFlowService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class OAuthFlowUserModel extends UserModel
{
    protected $validationRules = [];
}

final class OAuthFlowServiceTest extends CIUnitTestCase
{
    private BaseConnection $oauthDb;
    private OAuthFlowService $flows;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oauthDb = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $this->oauthDb->query(
            'CREATE TABLE users ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, '
            . 'email TEXT UNIQUE, password_hash TEXT, role TEXT, avatar_url TEXT, '
            . 'created_at TEXT, updated_at TEXT, deleted_at TEXT)'
        );
        $this->oauthDb->query(
            'CREATE TABLE oauth_identities ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, provider TEXT, '
            . 'provider_subject TEXT, provider_email TEXT, created_at TEXT, updated_at TEXT, '
            . 'UNIQUE(provider, provider_subject), UNIQUE(user_id, provider))'
        );
        $this->oauthDb->query(
            'CREATE TABLE oauth_authorization_states ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, state_hash TEXT UNIQUE, '
            . 'browser_token_hash TEXT, provider TEXT, intent TEXT, user_id INTEGER, '
            . 'expires_at TEXT, consumed_at TEXT, created_at TEXT)'
        );
        $this->oauthDb->query(
            'CREATE TABLE oauth_login_codes ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, code_hash TEXT UNIQUE, '
            . 'user_id INTEGER, expires_at TEXT, consumed_at TEXT, created_at TEXT)'
        );

        $users = new OAuthFlowUserModel($this->oauthDb);
        $this->flows = new OAuthFlowService(
            $this->oauthDb,
            $users,
            new AuthAccountService($this->oauthDb, $users)
        );
    }

    protected function tearDown(): void
    {
        $this->oauthDb->close();
        parent::tearDown();
    }

    public function testAuthorizationStateIsBoundToTheStartingBrowser(): void
    {
        $flow = $this->flows->createState('google', 'login', null);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $flow['state']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $flow['browserToken']);
        $this->assertNotSame($flow['state'], $flow['browserToken']);

        try {
            $this->flows->consumeState(
                'google',
                $flow['state'],
                str_repeat('a', 43)
            );
            $this->fail('A state from another browser must not be accepted.');
        } catch (AuthException $exception) {
            $this->assertSame('oauth_state_invalid', $exception->errorCode());
        }

        $state = $this->flows->consumeState(
            'google',
            $flow['state'],
            $flow['browserToken']
        );

        $this->assertSame('google', $state['provider']);
        $this->assertSame('login', $state['intent']);
    }

    public function testAuthorizationStateCanOnlyBeConsumedOnce(): void
    {
        $flow = $this->flows->createState('discord', 'login', null);
        $this->flows->consumeState('discord', $flow['state'], $flow['browserToken']);

        $this->expectException(AuthException::class);
        $this->flows->consumeState('discord', $flow['state'], $flow['browserToken']);
    }

    public function testVerifiedProviderIdentityRegistersAndSignsInOneAccount(): void
    {
        $identity = [
            'provider' => 'google',
            'subject' => 'google-user-42',
            'email' => 'player@example.test',
            'email_verified' => true,
            'name' => 'Player One',
            'avatar_url' => 'https://images.example/player.png',
        ];

        $registered = $this->flows->loginOrCreate($identity);
        $returning = $this->flows->loginOrCreate($identity);

        $this->assertGreaterThan(0, (int) $registered['id']);
        $this->assertSame($registered['id'], $returning['id']);
        $this->assertSame('player@example.test', $registered['email']);
        $this->assertSame(1, $this->oauthDb->table('users')->countAllResults());
        $this->assertSame(1, $this->oauthDb->table('oauth_identities')->countAllResults());

        $code = $this->flows->issueLoginCode((int) $registered['id']);
        $signedIn = $this->flows->consumeLoginCode($code);

        $this->assertSame($registered['id'], $signedIn['id']);

        try {
            $this->flows->consumeLoginCode($code);
            $this->fail('An application login code must only be usable once.');
        } catch (AuthException $exception) {
            $this->assertSame('oauth_code_invalid', $exception->errorCode());
        }
    }
}
