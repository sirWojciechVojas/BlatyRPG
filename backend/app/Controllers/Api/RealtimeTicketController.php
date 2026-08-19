<?php

namespace App\Controllers\Api;

use App\Services\Realtime\RealtimeTicketService;

class RealtimeTicketController extends CampaignApiController
{
    private $tickets;

    public function __construct()
    {
        parent::__construct();
        $this->tickets = new RealtimeTicketService();
    }

    public function create($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            $payload = $this->jsonPayload();
            return $this->tickets->issue(
                $this->auth(),
                $this->positiveId($campaignId, 'campaign_not_found'),
                trim((string) ($payload['clientInstanceId'] ?? ''))
            );
        }, 201);
    }
}
