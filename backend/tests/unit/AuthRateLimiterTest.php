<?php

use App\Services\Auth\AuthRateLimiter;
use CodeIgniter\Test\CIUnitTestCase;

final class AuthRateLimiterTest extends CIUnitTestCase
{
    public function testUsesHashedIpAndIdentityBucketsWithoutPlainPii(): void
    {
        $keys = [];
        $limiter = new AuthRateLimiter(static function ($key, $capacity, $seconds) use (&$keys): array {
            $keys[] = $key;
            return ['allowed' => true, 'retry_after' => 0];
        });

        $result = $limiter->consume('login', '192.0.2.1', 'Person@example.com');

        $this->assertTrue($result['allowed']);
        $this->assertCount(2, $keys);
        $this->assertStringNotContainsString('192.0.2.1', implode(' ', $keys));
        $this->assertStringNotContainsString('person@example.com', implode(' ', $keys));
    }

    public function testDenialReturnsRetryAfter(): void
    {
        $calls = 0;
        $limiter = new AuthRateLimiter(static function () use (&$calls): array {
            $calls++;
            return $calls === 1
                ? ['allowed' => false, 'retry_after' => 17]
                : ['allowed' => true, 'retry_after' => 0];
        });

        $result = $limiter->consume('reset_request', '192.0.2.1', 'person@example.com');

        $this->assertFalse($result['allowed']);
        $this->assertSame(17, $result['retry_after']);
    }
}
