<?php

namespace App\Services\Realtime;

use App\Services\Campaign\CampaignException;
use Firebase\JWT\JWT;

final class RealtimeTicketSigner
{
    private const ISSUER = 'BlatyRPG';
    private const AUDIENCE = 'blatyrpg-realtime';
    private const MAX_TTL = 60;

    public function issue(array $context, string $clientInstanceId): array
    {
        $secret = trim((string) getenv('REALTIME_TICKET_SECRET'));
        if (strlen($secret) < 32) {
            throw new CampaignException(
                'realtime_unavailable',
                'Realtime service is not configured.',
                503
            );
        }
        $now = time();
        $auth = $context['auth'];
        $accessExpiry = (int) (
            $auth['expires_at'] ?? $auth['token_expires_at'] ?? $auth['exp'] ?? 0
        );
        if ($accessExpiry <= $now) {
            throw new CampaignException('unauthorized', 'Authentication has expired.', 401);
        }
        $authSessionId = (int) ($auth['session_id'] ?? 0);
        if ($authSessionId < 1) {
            throw new CampaignException('unauthorized', 'Authentication session is invalid.', 401);
        }
        $expiresAt = min($now + self::MAX_TTL, $accessExpiry);
        $payload = [
            'iss' => $this->claimValue('REALTIME_TICKET_ISSUER', self::ISSUER),
            'aud' => $this->claimValue('REALTIME_TICKET_AUDIENCE', self::AUDIENCE),
            'sub' => (string) $auth['user_id'],
            'jti' => bin2hex(random_bytes(16)),
            'iat' => $now,
            'nbf' => $now - 1,
            'exp' => $expiresAt,
            'campaign_id' => (int) $context['campaign']['id'],
            'auth_session_id' => $authSessionId,
            'client_instance_id' => $clientInstanceId,
            'display_name' => (string) ($context['user']['username'] ?? ''),
            'avatar_url' => $context['user']['avatar_url'] ?? null,
            'campaign_role' => (string) $context['accessRole'],
            'capabilities' => [
                'canManage' => !empty($context['capabilities']['canManage']),
                'canViewHidden' => !empty($context['capabilities']['canViewHidden']),
            ],
        ];
        return [
            'ticket' => JWT::encode($payload, $secret, 'HS256'),
            'expiresAt' => $expiresAt,
            'expiresIn' => $expiresAt - $now,
            'campaignId' => (int) $context['campaign']['id'],
        ];
    }

    private function claimValue(string $environmentKey, string $default): string
    {
        $value = trim((string) (getenv($environmentKey) ?: $default));
        if ($value === '' || strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new CampaignException(
                'realtime_unavailable',
                'Realtime service is not configured.',
                503
            );
        }
        return $value;
    }
}
