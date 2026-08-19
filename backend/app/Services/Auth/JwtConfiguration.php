<?php

namespace App\Services\Auth;

final class JwtConfiguration
{
    private const DEFAULT_ISSUER = 'BlatyRPG';
    private const DEFAULT_AUDIENCE = 'BlatyRPG-API';
    private const MIN_SECRET_BYTES = 32;
    private const MIN_TTL = 60;
    private const MAX_TTL = 86400;

    public function values(): array
    {
        $secret = trim((string) getenv('JWT_SECRET'));
        $ttl = filter_var(getenv('JWT_TIME_TO_LIVE'), FILTER_VALIDATE_INT);
        $issuer = trim((string) (getenv('JWT_ISSUER') ?: self::DEFAULT_ISSUER));
        $audience = trim((string) (getenv('JWT_AUDIENCE') ?: self::DEFAULT_AUDIENCE));

        if (strlen($secret) < self::MIN_SECRET_BYTES) {
            throw new AuthConfigurationException('JWT secret is not configured securely.');
        }
        if ($ttl === false || $ttl < self::MIN_TTL || $ttl > self::MAX_TTL) {
            throw new AuthConfigurationException('JWT lifetime is outside the supported range.');
        }
        if (!$this->validClaimValue($issuer) || !$this->validClaimValue($audience)) {
            throw new AuthConfigurationException('JWT issuer or audience is invalid.');
        }

        return [
            'secret' => $secret,
            'ttl' => (int) $ttl,
            'issuer' => $issuer,
            'audience' => $audience,
        ];
    }

    private function validClaimValue(string $value): bool
    {
        return $value !== '' && strlen($value) <= 255
            && !preg_match('/[\x00-\x1F\x7F]/', $value);
    }
}
