<?php

use App\Services\Chat\CampaignChatException;
use App\Services\Realtime\RealtimePrincipalService;
use App\Services\Realtime\RealtimeTicketVerifier;
use CodeIgniter\Test\CIUnitTestCase;
use Firebase\JWT\JWT;

/** @internal */
final class RealtimePrincipalServiceTest extends CIUnitTestCase
{
    private const SECRET = 'ssssssssssssssssssssssssssssssssssssssssssssssss';
    private const CLIENT = 'client_instance_123456';

    public function testResolvesAnActiveLoginSession(): void
    {
        $service = $this->service([
            'id' => 19,
            'user_id' => 7,
            'expires_at' => date('Y-m-d H:i:s', time() + 600),
            'revoked_at' => null,
        ]);

        $principal = $service->resolve(
            'Realtime ' . $this->ticket(),
            self::CLIENT,
            31
        );

        $this->assertSame(7, $principal['user_id']);
        $this->assertSame(19, $principal['session_id']);
        $this->assertFalse($principal['anonymous']);
    }

    public function testRejectsRevokedSession(): void
    {
        $service = $this->service([
            'id' => 19,
            'user_id' => 7,
            'expires_at' => date('Y-m-d H:i:s', time() + 600),
            'revoked_at' => date('Y-m-d H:i:s'),
        ]);
        $this->expectException(CampaignChatException::class);
        $service->resolve('Realtime ' . $this->ticket(), self::CLIENT, 31);
    }

    public function testRejectsRouteCampaignManipulation(): void
    {
        $service = $this->service([
            'id' => 19,
            'user_id' => 7,
            'expires_at' => date('Y-m-d H:i:s', time() + 600),
            'revoked_at' => null,
        ]);
        try {
            $service->resolve('Realtime ' . $this->ticket(), self::CLIENT, 99);
            $this->fail('Campaign mismatch should be rejected.');
        } catch (CampaignChatException $exception) {
            $this->assertSame('forbidden', $exception->errorCode());
            $this->assertSame(403, $exception->status());
        }
    }

    private function service(array $session): RealtimePrincipalService
    {
        return new RealtimePrincipalService(
            new RealtimeTicketVerifier(self::SECRET),
            static function (int $id) use ($session): ?array {
                return $id === 19 ? $session : null;
            }
        );
    }

    private function ticket(): string
    {
        $now = time();
        return JWT::encode([
            'iss' => 'BlatyRPG',
            'aud' => 'blatyrpg-realtime',
            'sub' => '7',
            'jti' => str_repeat('b', 32),
            'iat' => $now,
            'nbf' => $now - 1,
            'exp' => $now + 50,
            'campaign_id' => 31,
            'auth_session_id' => 19,
            'client_instance_id' => self::CLIENT,
        ], self::SECRET, 'HS256');
    }
}
