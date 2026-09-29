<?php

namespace App\Controllers\Api;

use App\Services\Token\TokenMovementRequestService;

class TokenMovementRequestController extends CampaignApiController
{
    private $requests;

    public function __construct()
    {
        parent::__construct();
        $this->requests = new TokenMovementRequestService();
    }

    public function index($campaignId = null)
    {
        return $this->execute(fn (): array => $this->requests->listPending(
            $this->positiveId($campaignId), $this->auth()
        ));
    }
}
