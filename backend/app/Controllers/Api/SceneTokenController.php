<?php

namespace App\Controllers\Api;

use App\Services\Token\SceneTokenService;

class SceneTokenController extends CampaignApiController
{
    private $tokens;

    public function __construct()
    {
        parent::__construct();
        $this->tokens = new SceneTokenService();
    }

    public function index($campaignId = null, $sceneId = null)
    {
        return $this->execute(fn (): array => $this->tokens->list(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->auth()
        ));
    }

    public function create($campaignId = null, $sceneId = null)
    {
        return $this->execute(fn (): array => $this->tokens->create(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->auth(),
            $this->jsonPayload()
        ), 201);
    }

    public function update($campaignId = null, $sceneId = null, $tokenId = null)
    {
        return $this->execute(fn (): array => $this->tokens->update(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->positiveId($tokenId),
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function delete($campaignId = null, $sceneId = null, $tokenId = null)
    {
        return $this->execute(fn (): array => $this->tokens->delete(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->positiveId($tokenId),
            $this->auth(),
            $this->jsonPayload()
        ));
    }
}
