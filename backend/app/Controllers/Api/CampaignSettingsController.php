<?php

namespace App\Controllers\Api;

use App\Services\Campaign\CampaignSettingsService;

class CampaignSettingsController extends CampaignApiController
{
    private $settings;

    public function __construct()
    {
        parent::__construct();
        $this->settings = new CampaignSettingsService();
    }

    public function show($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->settings->show(
                $this->auth(),
                $this->positiveId($campaignId, 'campaign_not_found')
            );
        });
    }

    public function update($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->settings->update(
                $this->auth(),
                $this->positiveId($campaignId, 'campaign_not_found'),
                $this->jsonPayload()
            );
        });
    }

    public function enter($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->settings->enter(
                $this->auth(),
                $this->positiveId($campaignId, 'campaign_not_found')
            );
        });
    }
}
