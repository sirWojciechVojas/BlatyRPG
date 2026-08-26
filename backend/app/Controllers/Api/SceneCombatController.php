<?php

namespace App\Controllers\Api;

use App\Services\Combat\SceneCombatService;

class SceneCombatController extends CampaignApiController
{
    private $combats;

    public function __construct()
    {
        parent::__construct();
        $this->combats = new SceneCombatService();
    }

    public function show($campaignId = null, $sceneId = null)
    {
        return $this->execute(fn (): array => $this->combats->get(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->auth()
        ));
    }

    public function command($campaignId = null, $sceneId = null)
    {
        return $this->execute(fn (): array => $this->combats->command(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->auth(),
            $this->jsonPayload()
        ));
    }
}
