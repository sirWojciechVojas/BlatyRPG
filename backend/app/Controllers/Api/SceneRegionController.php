<?php

namespace App\Controllers\Api;

use App\Services\Region\SceneRegionService;

class SceneRegionController extends CampaignApiController
{
    private $regions;

    public function __construct()
    {
        parent::__construct();
        $this->regions = new SceneRegionService();
    }

    public function index($campaignId = null, $sceneId = null)
    {
        return $this->execute(fn (): array => $this->regions->list(
            $this->positiveId($campaignId), $this->positiveId($sceneId), $this->auth()
        ));
    }

    public function create($campaignId = null, $sceneId = null)
    {
        return $this->execute(fn (): array => $this->regions->create(
            $this->positiveId($campaignId), $this->positiveId($sceneId),
            $this->auth(), $this->jsonPayload()
        ), 201);
    }

    public function update($campaignId = null, $sceneId = null, $regionId = null)
    {
        return $this->execute(fn (): array => $this->regions->update(
            $this->positiveId($campaignId), $this->positiveId($sceneId),
            $this->positiveId($regionId), $this->auth(), $this->jsonPayload()
        ));
    }

    public function delete($campaignId = null, $sceneId = null, $regionId = null)
    {
        return $this->execute(fn (): array => $this->regions->delete(
            $this->positiveId($campaignId), $this->positiveId($sceneId),
            $this->positiveId($regionId), $this->auth(), $this->jsonPayload()
        ));
    }
}
