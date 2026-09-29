<?php

namespace App\Controllers\Api;

use App\Services\Light\SceneLightService;

class SceneLightController extends CampaignApiController
{
    private $lights;

    public function __construct()
    {
        parent::__construct();
        $this->lights = new SceneLightService();
    }

    public function index($campaignId = null, $sceneId = null)
    {
        return $this->execute(fn (): array => $this->lights->list(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->auth()
        ));
    }

    public function create($campaignId = null, $sceneId = null)
    {
        return $this->execute(fn (): array => $this->lights->create(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->auth(),
            $this->jsonPayload()
        ), 201);
    }

    public function update($campaignId = null, $sceneId = null, $lightId = null)
    {
        return $this->execute(fn (): array => $this->lights->update(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->positiveId($lightId),
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function delete($campaignId = null, $sceneId = null, $lightId = null)
    {
        return $this->execute(fn (): array => $this->lights->delete(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->positiveId($lightId),
            $this->auth(),
            $this->jsonPayload()
        ));
    }
}
