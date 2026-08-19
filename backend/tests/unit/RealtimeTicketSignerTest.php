<?php

use App\Services\Realtime\RealtimeTicketSigner;
use CodeIgniter\Test\CIUnitTestCase;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/** @internal */
final class RealtimeTicketSignerTest extends CIUnitTestCase
{
    private $previousSecret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousSecret = getenv('REALTIME_TICKET_SECRET');
        putenv('REALTIME_TICKET_SECRET=' . str_repeat('s', 48));
    }

    protected function tearDown(): void
    {
        if ($this->previousSecret === false) {
            putenv('REALTIME_TICKET_SECRET');
        } else {
            putenv('REALTIME_TICKET_SECRET=' . $this->previousSecret);
        }
        parent::tearDown();
    }

    public function testTicketIsBoundToUserCampaignAndShortExpiry(): void
    {
        $result = (new RealtimeTicketSigner())->issue([
            'auth' => ['user_id' => 7, 'session_id' => 19, 'expires_at' => time() + 3600],
            'user' => ['username' => 'alice', 'avatar_url' => null],
            'campaign' => ['id' => 31],
            'accessRole' => 'player',
            'capabilities' => ['canManage' => false, 'canViewHidden' => false],
        ], 'client_instance_123456');
        $decoded = JWT::decode($result['ticket'], new Key(str_repeat('s', 48), 'HS256'));

        $this->assertSame('7', $decoded->sub);
        $this->assertSame(31, $decoded->campaign_id);
        $this->assertSame(19, $decoded->auth_session_id);
        $this->assertSame('client_instance_123456', $decoded->client_instance_id);
        $this->assertSame('blatyrpg-realtime', $decoded->aud);
        $this->assertLessThanOrEqual(60, $result['expiresIn']);
        $this->assertNotEmpty($decoded->jti);
    }
}
