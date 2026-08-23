<?php

namespace Tests\Unit;

use App\Services\Auth\AuthSessionPresenter;
use CodeIgniter\Test\CIUnitTestCase;

final class AuthSessionPresenterTest extends CIUnitTestCase
{
    public function testPresentsOnlySafeSessionMetadata(): void
    {
        $record = [
            'id' => 17,
            'user_id' => 4,
            'token_hash' => 'secret-token-hash',
            'jti_hash' => 'secret-jti-hash',
            'ip_hash' => 'secret-ip-hash',
            'user_agent_hash' => 'secret-agent-hash',
            'created_at' => '2026-08-23 10:00:00',
            'last_seen_at' => '2026-08-23 11:00:00',
            'expires_at' => '2026-08-24 10:00:00',
        ];

        $result = (new AuthSessionPresenter())->present($record, 17);

        $this->assertSame([
            'id' => 17,
            'isCurrent' => true,
            'createdAt' => '2026-08-23 10:00:00',
            'lastSeenAt' => '2026-08-23 11:00:00',
            'expiresAt' => '2026-08-24 10:00:00',
        ], $result);
        $this->assertArrayNotHasKey('token_hash', $result);
        $this->assertArrayNotHasKey('ip_hash', $result);
    }
}
