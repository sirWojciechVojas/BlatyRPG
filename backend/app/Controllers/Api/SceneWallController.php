<?php

namespace App\Controllers\Api;

use App\Services\Wall\SceneWallService;

class SceneWallController extends CampaignApiController
{
    private $walls;

    public function __construct()
    {
        parent::__construct();
        $this->walls = new SceneWallService();
    }

    public function index($campaignId = null, $sceneId = null)
    {
        return $this->execute(fn (): array => $this->walls->list(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->auth()
        ));
    }

    public function create($campaignId = null, $sceneId = null)
    {
        return $this->execute(fn (): array => $this->walls->create(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->auth(),
            $this->jsonPayload()
        ), 201);
    }

    public function update($campaignId = null, $sceneId = null, $wallId = null)
    {
        return $this->execute(fn (): array => $this->walls->update(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->positiveId($wallId),
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function delete($campaignId = null, $sceneId = null, $wallId = null)
    {
        return $this->execute(fn (): array => $this->walls->delete(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->positiveId($wallId),
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function interact($campaignId = null, $sceneId = null, $wallId = null)
    {
        return $this->execute(fn (): array => $this->walls->interact(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->positiveId($wallId),
            $this->auth(),
            $this->jsonPayload()
        ));
    }
}
