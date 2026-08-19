<?php

namespace App\Services\Auth;

use Config\Services;

final class AuthRateLimiter
{
    private const LIMITS = [
        'login' => [8, 60],
        'register' => [5, 3600],
        'reset_request' => [5, 900],
        'reset_confirm' => [10, 900],
    ];

    private $checker;

    public function __construct(?callable $checker = null)
    {
        if ($checker) {
            $this->checker = $checker;
            return;
        }
        $throttler = Services::throttler();
        $this->checker = static function (string $key, int $capacity, int $seconds) use ($throttler): array {
            $allowed = $throttler->check($key, $capacity, $seconds);
            return ['allowed' => $allowed, 'retry_after' => $throttler->getTokenTime()];
        };
    }

    public function consume(string $action, string $ip, string $identity = ''): array
    {
        if (!isset(self::LIMITS[$action])) {
            throw new \InvalidArgumentException('Unknown authentication rate limit.');
        }
        [$capacity, $seconds] = self::LIMITS[$action];
        $keys = [$this->key($action, 'ip', $ip ?: 'unknown')];
        $identity = strtolower(trim($identity));
        if ($identity !== '') {
            $keys[] = $this->key($action, 'identity', $identity);
        }
        $retryAfter = 0;
        foreach ($keys as $key) {
            $result = ($this->checker)($key, $capacity, $seconds);
            if (empty($result['allowed'])) {
                $retryAfter = max($retryAfter, (int) ($result['retry_after'] ?? 1));
            }
        }
        return ['allowed' => $retryAfter === 0, 'retry_after' => max(1, $retryAfter)];
    }

    private function key(string $action, string $scope, string $value): string
    {
        return 'auth_' . $action . '_' . $scope . '_' . hash('sha256', $value);
    }
}
