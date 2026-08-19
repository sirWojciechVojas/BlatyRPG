<?php

namespace App\Services\Auth;

use App\Models\AuthSessionModel;
use App\Models\UserModel;

final class AuthSessionService
{
    private $sessions;
    private $users;
    private $tokens;
    private $policy;

    public function __construct(
        ?AuthSessionModel $sessions = null,
        ?UserModel $users = null,
        ?AuthTokenService $tokens = null,
        ?AuthSessionPolicy $policy = null
    ) {
        $this->sessions = $sessions ?: new AuthSessionModel();
        $this->users = $users ?: new UserModel();
        $this->tokens = $tokens ?: new AuthTokenService();
        $this->policy = $policy ?: new AuthSessionPolicy();
    }

    public function issue(array $user, ?string $ip = null, ?string $userAgent = null): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $role = UserRole::normalize($user['role'] ?? '');
        if ($userId < 1 || !UserRole::isSupported($role)) {
            throw new AuthException('session_write_failed', 'Authentication session could not be created.', 500);
        }
        try {
            $jti = bin2hex(random_bytes(16));
            $issued = $this->tokens->issue($userId, $role, $jti);
        } catch (AuthConfigurationException $exception) {
            throw new AuthException('auth_unavailable', 'Authentication is temporarily unavailable.', 503);
        } catch (\Throwable $exception) {
            throw new AuthException('session_write_failed', 'Authentication session could not be created.', 500);
        }

        $claims = $issued['claims'];
        $inserted = $this->sessions->insert([
            'user_id' => $userId,
            'jti_hash' => hash('sha256', $jti),
            'token_hash' => hash('sha256', $issued['token']),
            'expires_at' => date('Y-m-d H:i:s', (int) $claims['exp']),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'ip_hash' => $this->optionalHash($ip),
            'user_agent_hash' => $this->optionalHash($userAgent),
        ]);
        if (!$inserted) {
            throw new AuthException('session_write_failed', 'Authentication session could not be created.', 500);
        }

        return [
            'access_token' => $issued['token'],
            'token_type' => 'Bearer',
            'expires_in' => (int) $issued['expires_in'],
            'session_id' => (int) $this->sessions->getInsertID(),
        ];
    }

    public function resolveAuthorizationHeader(?string $header): array
    {
        if (!$header || !preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $matches)) {
            return $this->anonymous('invalid_token');
        }
        return $this->resolveToken($matches[1]);
    }

    public function resolveToken(string $token): array
    {
        $decoded = $this->tokens->decode($token);
        if (empty($decoded['valid'])) {
            return $this->anonymous(!empty($decoded['configuration_error'])
                ? 'configuration_error' : 'invalid_token');
        }

        $session = $this->sessions
            ->where('token_hash', hash('sha256', $token))
            ->where('jti_hash', hash('sha256', $decoded['jti']))
            ->first();
        $user = $session ? $this->users->withDeleted()->find((int) $session['user_id']) : null;
        $principal = $this->policy->resolve($decoded, $session, $user);
        if (empty($principal['valid'])) {
            return $this->anonymous('invalid_token');
        }
        $this->touch($session);

        return [
            'user_id' => $principal['user_id'],
            'role' => $principal['role'],
            'session_id' => $principal['session_id'],
            'expires_at' => $principal['expires_at'],
            'anonymous' => false,
            'authenticated' => true,
            'authentication_error' => null,
        ];
    }

    public function revoke(int $sessionId, int $userId): bool
    {
        if ($sessionId < 1 || $userId < 1) {
            return false;
        }
        return (bool) $this->sessions->where('id', $sessionId)
            ->where('user_id', $userId)
            ->where('revoked_at', null)
            ->set(['revoked_at' => date('Y-m-d H:i:s')])
            ->update();
    }

    public function revokeAll(int $userId): void
    {
        if ($userId < 1) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $this->sessions->where('user_id', $userId)
            ->where('revoked_at', null)
            ->set(['revoked_at' => $now])
            ->update();
    }

    private function touch(array $session): void
    {
        $lastSeen = strtotime((string) ($session['last_seen_at'] ?? '')) ?: 0;
        if ($lastSeen > time() - 60) {
            return;
        }
        $this->sessions->update((int) $session['id'], [
            'last_seen_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function optionalHash(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : hash('sha256', $value);
    }

    private function anonymous(string $error): array
    {
        return [
            'user_id' => null,
            'role' => null,
            'session_id' => null,
            'expires_at' => null,
            'anonymous' => true,
            'authenticated' => false,
            'authentication_error' => $error,
        ];
    }
}
