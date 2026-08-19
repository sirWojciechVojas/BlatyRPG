<?php

use App\Services\Chat\CampaignChatException;
use App\Services\Realtime\RealtimeTicketVerifier;
use CodeIgniter\Test\CIUnitTestCase;
use Firebase\JWT\JWT;

/** @internal */
final class RealtimeTicketVerifierTest extends CIUnitTestCase
{
    private const SECRET = 'ssssssssssssssssssssssssssssssssssssssssssssssss';
    private const CLIENT = 'client_instance_123456';

    public function testVerifiesBoundUserCampaignAndSession(): void
    {
        $claims = (new RealtimeTicketVerifier(self::SECRET))->verify(
            $this->ticket(),
            self::CLIENT
        );

        $this->assertSame(7, $claims['user_id']);
        $this->assertSame(31, $claims['campaign_id']);
        $this->assertSame(19, $claims['session_id']);
    }

    public function testRejectsClientInstanceMismatch(): void
    {
        $this->expectException(CampaignChatException::class);
        (new RealtimeTicketVerifier(self::SECRET))->verify(
            $this->ticket(),
            'another_instance_12345'
        );
    }

    public function testRejectsMissingSessionClaim(): void
    {
        $this->expectException(CampaignChatException::class);
        $ticket = $this->ticket(['auth_session_id' => null]);
        (new RealtimeTicketVerifier(self::SECRET))->verify($ticket, self::CLIENT);
    }

    private function ticket(array $overrides = []): string
    {
        $now = time();
        $claims = array_merge([
            'iss' => 'BlatyRPG',
            'aud' => 'blatyrpg-realtime',
            'sub' => '7',
            'jti' => str_repeat('a', 32),
            'iat' => $now,
            'nbf' => $now - 1,
            'exp' => $now + 50,
            'campaign_id' => 31,
            'auth_session_id' => 19,
            'client_instance_id' => self::CLIENT,
        ], $overrides);
        return JWT::encode($claims, self::SECRET, 'HS256');
    }
}
