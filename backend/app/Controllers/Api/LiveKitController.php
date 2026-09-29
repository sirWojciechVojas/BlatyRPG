<?php

namespace App\Controllers\Api;

use App\Services\Voice\LiveKitAccessTokenService;

class LiveKitController extends CampaignApiController
{
    private $tokens;

    public function __construct()
    {
        parent::__construct();
        $this->tokens = new LiveKitAccessTokenService();
    }

    public function token($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->tokens->issue(
                $this->auth(),
                $this->positiveId($campaignId, 'campaign_not_found')
            );
        }, 201);
    }
}
