<?php

namespace App\Controllers\Api;

use App\Services\Token\TokenSyncService;

class TokenSyncController extends CampaignApiController
{
    private $sync;

    public function __construct()
    {
        parent::__construct();
        $this->sync = new TokenSyncService();
    }

    public function index($campaignId = null)
    {
        return $this->execute(fn (): array => $this->sync->catalog(
            $this->positiveId($campaignId), $this->auth()
        ));
    }

    public function preview($campaignId = null)
    {
        return $this->execute(fn (): array => $this->sync->preview(
            $this->positiveId($campaignId), $this->auth(), $this->jsonPayload()
        ));
    }

    public function transfer($campaignId = null)
    {
        return $this->execute(fn (): array => $this->sync->transfer(
            $this->positiveId($campaignId), $this->auth(), $this->jsonPayload()
        ));
    }

    public function createLinks($campaignId = null)
    {
        return $this->execute(fn (): array => $this->sync->createLinks(
            $this->positiveId($campaignId), $this->auth(), $this->jsonPayload()
        ), 201);
    }

    public function updateLink($campaignId = null, $linkId = null)
    {
        return $this->execute(fn (): array => $this->sync->updateLink(
            $this->positiveId($campaignId), $this->positiveId($linkId),
            $this->auth(), $this->jsonPayload()
        ));
    }

    public function applyLink($campaignId = null, $linkId = null)
    {
        return $this->execute(fn (): array => $this->sync->applyLink(
            $this->positiveId($campaignId), $this->positiveId($linkId), $this->auth()
        ));
    }

    public function deleteLink($campaignId = null, $linkId = null)
    {
        return $this->execute(fn (): array => $this->sync->deleteLink(
            $this->positiveId($campaignId), $this->positiveId($linkId), $this->auth()
        ));
    }
}
