<?php

namespace App\Services\Realtime;

/** Best-effort invalidation for clients displaying campaign character lists. */
final class CharacterAccessRealtimePublisher
{
    private $url;
    private $secret;

    public function __construct(?string $url = null, ?string $secret = null)
    {
        $this->url = trim((string) ($url ?? getenv('REALTIME_CHARACTER_ACCESS_PUBLISH_URL')));
        $this->secret = trim((string) ($secret ?? getenv('REALTIME_TICKET_SECRET')));
    }

    public function publish(int $campaignId, int $characterId, int $actorUserId): bool
    {
        if ($this->url === '' || strlen($this->secret) < 32) {
            return false;
        }
        $body = json_encode([
            'type' => 'character.access.changed',
            'campaignId' => $campaignId,
            'characterId' => $characterId,
            'actorUserId' => $actorUserId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) {
            return false;
        }
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $this->secret);
        try {
            $response = service('curlrequest')->post($this->url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Realtime-Timestamp' => $timestamp,
                    'X-Realtime-Signature' => $signature,
                ],
                'body' => $body,
                'timeout' => 2,
                'http_errors' => false,
            ]);
            return $response->getStatusCode() === 202;
        } catch (\Throwable $exception) {
            log_message('warning', 'Character access realtime publish failed: {message}', [
                'message' => $exception->getMessage(),
            ]);
            return false;
        }
    }
}
