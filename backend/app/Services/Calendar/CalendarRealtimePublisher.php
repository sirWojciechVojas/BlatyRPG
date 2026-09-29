<?php

namespace App\Services\Calendar;

final class CalendarRealtimePublisher
{
    private $url;
    private $secret;

    public function __construct(?string $url = null, ?string $secret = null)
    {
        $this->url = trim((string) ($url ?? getenv('REALTIME_CALENDAR_PUBLISH_URL')));
        $this->secret = trim((string) ($secret ?? getenv('REALTIME_TICKET_SECRET')));
    }

    public function publish(string $type, int $campaignId, int $revision, int $actorUserId, array $payload): bool
    {
        if ($this->url === '' || strlen($this->secret) < 32) {
            return false;
        }
        $body = json_encode([
            'type' => $type,
            'campaignId' => $campaignId,
            'revision' => $revision,
            'actorUserId' => $actorUserId,
            'payload' => $payload,
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
            log_message('warning', 'Calendar realtime publish failed: {message}', [
                'message' => $exception->getMessage(),
            ]);
            return false;
        }
    }
}
