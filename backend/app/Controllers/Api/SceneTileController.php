<?php

namespace App\Controllers\Api;

use App\Services\Tile\SceneTileService;

class SceneTileController extends CampaignApiController
{
    private $tiles;

    public function __construct()
    {
        parent::__construct();
        $this->tiles = new SceneTileService();
    }

    public function index($campaignId = null, $sceneId = null)
    {
        return $this->execute(fn (): array => $this->tiles->list(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->auth()
        ));
    }

    public function create($campaignId = null, $sceneId = null)
    {
        return $this->execute(fn (): array => $this->tiles->create(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->auth(),
            $this->jsonPayload()
        ), 201);
    }

    public function update($campaignId = null, $sceneId = null, $tileId = null)
    {
        return $this->execute(fn (): array => $this->tiles->update(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->positiveId($tileId),
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function delete($campaignId = null, $sceneId = null, $tileId = null)
    {
        return $this->execute(fn (): array => $this->tiles->delete(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->positiveId($tileId),
            $this->auth(),
            $this->jsonPayload()
        ));
    }
}
