<?php

namespace App\Services\Realtime;

use App\Services\Campaign\CampaignGuardService;

class RealtimeTicketService
{
    private $guard;
    private $signer;

    public function __construct(
        ?CampaignGuardService $guard = null,
        ?RealtimeTicketSigner $signer = null
    ) {
        $this->guard = $guard ?: new CampaignGuardService();
        $this->signer = $signer ?: new RealtimeTicketSigner();
    }

    public function issue(array $auth, int $campaignId, string $clientInstanceId): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{16,128}$/', $clientInstanceId)) {
            throw new \App\Services\Campaign\CampaignException(
                'validation_failed',
                'Client instance id is invalid.',
                422,
                ['clientInstanceId' => 'Use a stable 16-128 character identifier.']
            );
        }
        $context = $this->guard->context($auth, $campaignId);
        $this->guard->touchActivity($campaignId);
        return $this->signer->issue($context, $clientInstanceId);
    }
}
