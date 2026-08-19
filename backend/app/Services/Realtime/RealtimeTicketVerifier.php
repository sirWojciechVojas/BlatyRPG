<?php

namespace App\Services\Realtime;

use App\Services\Chat\CampaignChatException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/** Verifies a short-lived realtime ticket without consuming its WebSocket JTI. */
final class RealtimeTicketVerifier
{
    private const MAX_LIFETIME = 60;

    private $secret;
    private $issuer;
    private $audience;

    public function __construct(?string $secret = null, ?string $issuer = null, ?string $audience = null)
    {
        $this->secret = $secret ?? trim((string) getenv('REALTIME_TICKET_SECRET'));
        $this->issuer = $issuer ?? $this->setting('REALTIME_TICKET_ISSUER', 'BlatyRPG');
        $this->audience = $audience ?? $this->setting(
            'REALTIME_TICKET_AUDIENCE',
            'blatyrpg-realtime'
        );
    }

    public function verify(string $token, string $clientInstanceId): array
    {
        if (strlen($this->secret) < 32 || $token === '' || strlen($token) > 8192) {
            $this->deny();
        }
        if (!preg_match('/^[A-Za-z0-9_-]{16,128}$/', $clientInstanceId)) {
            $this->deny();
        }
        try {
            $claims = JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (\Throwable $exception) {
            $this->deny();
        }

        $now = time();
        $issuedAt = $this->integer($claims->iat ?? null);
        $notBefore = $this->integer($claims->nbf ?? null);
        $expiresAt = $this->integer($claims->exp ?? null);
        $userId = $this->positiveId($claims->sub ?? null);
        $campaignId = $this->positiveId($claims->campaign_id ?? null);
        $sessionId = $this->positiveId($claims->auth_session_id ?? null);
        $ticketClient = (string) ($claims->client_instance_id ?? '');
        $jti = (string) ($claims->jti ?? '');

        if (($claims->iss ?? null) !== $this->issuer
            || ($claims->aud ?? null) !== $this->audience
            || $issuedAt === null || $notBefore === null || $expiresAt === null
            || $userId === null || $campaignId === null || $sessionId === null
            || $issuedAt > $now + 2 || $notBefore > $now + 2 || $expiresAt <= $now
            || $expiresAt <= $issuedAt || $expiresAt - $issuedAt > self::MAX_LIFETIME
            || !preg_match('/^[a-f0-9]{32}$/', $jti)
            || $ticketClient !== $clientInstanceId) {
            $this->deny();
        }

        return [
            'user_id' => $userId,
            'campaign_id' => $campaignId,
            'session_id' => $sessionId,
            'expires_at' => $expiresAt,
            'client_instance_id' => $ticketClient,
        ];
    }

    private function setting(string $key, string $default): string
    {
        return trim((string) (getenv($key) ?: $default));
    }

    private function integer($value): ?int
    {
        return is_int($value) ? $value : null;
    }

    private function positiveId($value): ?int
    {
        if (is_string($value) && !preg_match('/^[1-9][0-9]*$/', $value)) {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $number === false ? null : (int) $number;
    }

    private function deny(): void
    {
        throw new CampaignChatException('unauthorized', 'Realtime authentication failed.', 401);
    }
}
