<?php

namespace App\Services\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class AuthTokenService
{
    private $configuration;

    public function __construct(?JwtConfiguration $configuration = null)
    {
        $this->configuration = $configuration ?: new JwtConfiguration();
    }

    public function issue(int $userId, string $role, string $jti, ?int $now = null): array
    {
        $config = $this->configuration->values();
        $issuedAt = $now ?? time();
        $expiresAt = $issuedAt + $config['ttl'];
        $role = UserRole::normalize($role);
        if ($userId < 1 || !UserRole::isSupported($role) || !$this->validJti($jti)) {
            throw new \InvalidArgumentException('Invalid JWT session claims.');
        }

        $claims = [
            'iss' => $config['issuer'],
            'aud' => $config['audience'],
            'sub' => (string) $userId,
            'role' => $role,
            'jti' => $jti,
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $expiresAt,
        ];

        return [
            'token' => JWT::encode($claims, $config['secret'], 'HS256'),
            'claims' => $claims,
            'expires_in' => $config['ttl'],
        ];
    }

    public function decode(string $token, ?int $now = null): array
    {
        try {
            $config = $this->configuration->values();
        } catch (AuthConfigurationException $exception) {
            return ['valid' => false, 'configuration_error' => true];
        }

        try {
            $claims = JWT::decode($token, new Key($config['secret'], 'HS256'));
        } catch (\Throwable $exception) {
            return ['valid' => false, 'configuration_error' => false];
        }

        $timestamp = $now ?? time();
        $subjectClaim = $claims->sub ?? null;
        $issuedClaim = $claims->iat ?? null;
        $notBeforeClaim = $claims->nbf ?? null;
        $expiresClaim = $claims->exp ?? null;
        $jti = is_string($claims->jti ?? null) ? $claims->jti : '';
        $role = is_string($claims->role ?? null)
            ? UserRole::normalize($claims->role) : '';
        $subject = is_string($subjectClaim) ? filter_var($subjectClaim, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]) : false;
        $issuedAt = is_int($issuedClaim) ? $issuedClaim : false;
        $notBefore = is_int($notBeforeClaim) ? $notBeforeClaim : false;
        $expiresAt = is_int($expiresClaim) ? $expiresClaim : false;

        $valid = is_string($claims->iss ?? null)
            && is_string($claims->aud ?? null)
            && $claims->iss === $config['issuer']
            && $claims->aud === $config['audience']
            && $subject !== false
            && $issuedAt !== false
            && $notBefore !== false
            && $expiresAt !== false
            && $issuedAt <= $timestamp + 30
            && $notBefore <= $timestamp + 30
            && $expiresAt > $timestamp
            && $expiresAt > $issuedAt
            && $expiresAt > $notBefore
            && $expiresAt <= $issuedAt + $config['ttl']
            && $notBefore >= $issuedAt
            && $this->validJti($jti)
            && UserRole::isSupported($role);

        if (!$valid) {
            return ['valid' => false, 'configuration_error' => false];
        }

        return [
            'valid' => true,
            'configuration_error' => false,
            'user_id' => (int) $subject,
            'role' => $role,
            'jti' => $jti,
            'issued_at' => (int) $issuedAt,
            'expires_at' => (int) $expiresAt,
        ];
    }

    private function validJti(string $jti): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/', $jti);
    }
}
