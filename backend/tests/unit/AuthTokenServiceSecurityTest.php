<?php

use App\Services\Auth\AuthTokenService;
use App\Services\Auth\JwtConfiguration;
use CodeIgniter\Test\CIUnitTestCase;
use Firebase\JWT\JWT;

final class AuthTokenServiceSecurityTest extends CIUnitTestCase
{
    private $previous = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['JWT_SECRET', 'JWT_TIME_TO_LIVE', 'JWT_ISSUER', 'JWT_AUDIENCE'] as $key) {
            $this->previous[$key] = getenv($key);
        }
        putenv('JWT_SECRET=' . str_repeat('s', 32));
        putenv('JWT_TIME_TO_LIVE=3600');
        putenv('JWT_ISSUER=BlatyRPG-Test');
        putenv('JWT_AUDIENCE=BlatyRPG-Test-API');
    }

    protected function tearDown(): void
    {
        foreach ($this->previous as $key => $value) {
            $value === false ? putenv($key) : putenv($key . '=' . $value);
        }
        parent::tearDown();
    }

    public function testIssuesStrictClaimsAndNormalizesLegacyPlayerRole(): void
    {
        $service = new AuthTokenService(new JwtConfiguration());
        $issued = $service->issue(7, 'user', str_repeat('a', 32));
        $decoded = $service->decode($issued['token']);

        $this->assertTrue($decoded['valid']);
        $this->assertSame(7, $decoded['user_id']);
        $this->assertSame('player', $decoded['role']);
        $this->assertSame(str_repeat('a', 32), $decoded['jti']);
        $this->assertSame(3600, $issued['expires_in']);
    }

    public function testRejectsSignedTokenWithWrongAudience(): void
    {
        $now = time();
        $token = JWT::encode([
            'iss' => 'BlatyRPG-Test',
            'aud' => 'another-api',
            'sub' => '7',
            'role' => 'player',
            'jti' => str_repeat('b', 32),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 3600,
        ], str_repeat('s', 32), 'HS256');

        $decoded = (new AuthTokenService())->decode($token);

        $this->assertFalse($decoded['valid']);
        $this->assertFalse($decoded['configuration_error']);
    }

    public function testRejectsMissingJtiAndExcessiveExpiry(): void
    {
        $now = time();
        $token = JWT::encode([
            'iss' => 'BlatyRPG-Test',
            'aud' => 'BlatyRPG-Test-API',
            'sub' => '7',
            'role' => 'player',
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 7200,
        ], str_repeat('s', 32), 'HS256');

        $this->assertFalse((new AuthTokenService())->decode($token)['valid']);
    }

    public function testRejectsNonCanonicalClaimTypesAndInvalidTimeOrder(): void
    {
        $now = time();
        $wrongSubjectType = JWT::encode([
            'iss' => 'BlatyRPG-Test',
            'aud' => 'BlatyRPG-Test-API',
            'sub' => 7,
            'role' => 'player',
            'jti' => str_repeat('c', 32),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 3600,
        ], str_repeat('s', 32), 'HS256');
        $invalidTimeOrder = JWT::encode([
            'iss' => 'BlatyRPG-Test',
            'aud' => 'BlatyRPG-Test-API',
            'sub' => '7',
            'role' => 'player',
            'jti' => str_repeat('d', 32),
            'iat' => $now + 20,
            'nbf' => $now,
            'exp' => $now + 10,
        ], str_repeat('s', 32), 'HS256');

        $service = new AuthTokenService();
        $this->assertFalse($service->decode($wrongSubjectType)['valid']);
        $this->assertFalse($service->decode($invalidTimeOrder)['valid']);
    }

    public function testMissingSecretFailsClosedWithoutLeakingConfiguration(): void
    {
        putenv('JWT_SECRET');

        $decoded = (new AuthTokenService())->decode('not-a-token');

        $this->assertFalse($decoded['valid']);
        $this->assertTrue($decoded['configuration_error']);
    }
}
