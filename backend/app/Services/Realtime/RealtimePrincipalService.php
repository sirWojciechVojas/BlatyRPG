<?php

namespace App\Services\Realtime;

use App\Models\AuthSessionModel;
use App\Services\Chat\CampaignChatException;

/** Resolves a realtime ticket to a currently active, revocable login session. */
final class RealtimePrincipalService
{
    private $verifier;
    private $sessionFinder;

    public function __construct(
        ?RealtimeTicketVerifier $verifier = null,
        ?callable $sessionFinder = null
    ) {
        $this->verifier = $verifier ?: new RealtimeTicketVerifier();
        $this->sessionFinder = $sessionFinder ?: static function (int $id): ?array {
            $session = (new AuthSessionModel())->find($id);
            return is_array($session) ? $session : null;
        };
    }

    public function resolve(
        string $authorization,
        string $clientInstanceId,
        int $routeCampaignId
    ): array {
        if (!preg_match('/^\s*Realtime\s+(\S+)\s*$/i', $authorization, $matches)) {
            $this->deny();
        }
        $claims = $this->verifier->verify($matches[1], $clientInstanceId);
        if ($routeCampaignId < 1 || $claims['campaign_id'] !== $routeCampaignId) {
            throw new CampaignChatException(
                'forbidden',
                'Realtime campaign scope does not match.',
                403
            );
        }

        $session = call_user_func($this->sessionFinder, $claims['session_id']);
        $sessionExpiry = is_array($session)
            ? strtotime((string) ($session['expires_at'] ?? '')) : false;
        if (!$session
            || (int) ($session['id'] ?? 0) !== $claims['session_id']
            || (int) ($session['user_id'] ?? 0) !== $claims['user_id']
            || !empty($session['revoked_at'])
            || $sessionExpiry === false || $sessionExpiry <= time()) {
            $this->deny();
        }

        return [
            'user_id' => $claims['user_id'],
            'session_id' => $claims['session_id'],
            'expires_at' => min($claims['expires_at'], $sessionExpiry),
            'anonymous' => false,
            'authenticated' => true,
        ];
    }

    private function deny(): void
    {
        throw new CampaignChatException('unauthorized', 'Realtime session is not active.', 401);
    }
}
