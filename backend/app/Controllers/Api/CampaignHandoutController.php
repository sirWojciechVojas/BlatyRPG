<?php

namespace App\Controllers\Api;

use App\Services\Handout\HandoutService;

class CampaignHandoutController extends CampaignApiController
{
    private $handouts;

    public function __construct()
    {
        parent::__construct();
        $this->handouts = new HandoutService();
    }

    public function index($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->handouts->listCampaign($this->positiveId($campaignId, 'campaign_not_found'), $this->auth(), $this->request->getGet());
        });
    }

    public function show($campaignId = null, $journalId = null)
    {
        return $this->execute(function () use ($campaignId, $journalId): array {
            return $this->handouts->getCampaignHandout($this->positiveId($campaignId, 'campaign_not_found'), $this->positiveId($journalId, 'handout_not_found'), $this->auth());
        });
    }

    public function publish($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->handouts->publish($this->positiveId($campaignId, 'campaign_not_found'), $this->auth(), $this->jsonPayload());
        }, 201);
    }

    public function update($campaignId = null, $journalId = null)
    {
        return $this->execute(function () use ($campaignId, $journalId): array {
            return $this->handouts->updateCampaignHandout($this->positiveId($campaignId, 'campaign_not_found'), $this->positiveId($journalId, 'handout_not_found'), $this->auth(), $this->jsonPayload());
        });
    }

    public function delete($campaignId = null, $journalId = null)
    {
        return $this->execute(function () use ($campaignId, $journalId): array {
            return $this->handouts->trashCampaignHandout($this->positiveId($campaignId, 'campaign_not_found'), $this->positiveId($journalId, 'handout_not_found'), $this->auth(), $this->jsonPayload());
        });
    }

    public function restore($campaignId = null, $journalId = null)
    {
        return $this->execute(function () use ($campaignId, $journalId): array {
            return $this->handouts->restoreCampaignHandout($this->positiveId($campaignId, 'campaign_not_found'), $this->positiveId($journalId, 'handout_not_found'), $this->auth());
        });
    }

    public function share($campaignId = null, $journalId = null)
    {
        return $this->execute(function () use ($campaignId, $journalId): array {
            return $this->handouts->share($this->positiveId($campaignId, 'campaign_not_found'), $this->positiveId($journalId, 'handout_not_found'), $this->auth(), $this->jsonPayload());
        });
    }

    public function transferAuthor($campaignId = null, $journalId = null)
    {
        return $this->execute(function () use ($campaignId, $journalId): array {
            return $this->handouts->transferAuthor($this->positiveId($campaignId, 'campaign_not_found'), $this->positiveId($journalId, 'handout_not_found'), $this->auth(), $this->jsonPayload());
        });
    }

    public function notifications($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->handouts->listNotifications($this->positiveId($campaignId, 'campaign_not_found'), $this->auth());
        });
    }

    public function readNotification($campaignId = null, $notificationId = null)
    {
        return $this->execute(function () use ($campaignId, $notificationId): array {
            return $this->handouts->markNotificationRead($this->positiveId($campaignId, 'campaign_not_found'), $this->positiveId($notificationId, 'handout_notification_not_found'), $this->auth());
        });
    }
}
