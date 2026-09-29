<?php

namespace App\Controllers\Api;

use App\Services\Compendium\CompendiumMaterializationService;
use App\Services\Compendium\CompendiumService;

final class CampaignCompendiumController extends CampaignApiController
{
    private $compendium;
    private $materialization;

    public function __construct()
    {
        parent::__construct();
        $this->compendium = new CompendiumService();
        $this->materialization = new CompendiumMaterializationService();
    }

    public function overview($campaignId = null)
    {
        return $this->execute(fn (): array => $this->compendium->campaignOverview(
            $this->positiveId($campaignId), $this->auth()
        ));
    }

    public function index($campaignId = null)
    {
        return $this->execute(fn (): array => $this->compendium->campaignIndex(
            $this->positiveId($campaignId), $this->auth(), $this->request->getGet()
        ));
    }

    public function timeline($campaignId = null)
    {
        return $this->execute(fn (): array => $this->compendium->campaignTimeline(
            $this->positiveId($campaignId), $this->auth(), $this->request->getGet()
        ));
    }

    public function show($campaignId = null, $entryId = null)
    {
        return $this->execute(fn (): array => $this->compendium->campaignShow(
            $this->positiveId($campaignId), $this->positiveId($entryId, 'compendium_entry_not_found'), $this->auth()
        ));
    }

    public function materialize($campaignId = null, $entryId = null)
    {
        return $this->execute(fn (): array => $this->materialization->materialize(
            $this->positiveId($campaignId), $this->positiveId($entryId, 'compendium_entry_not_found'),
            $this->auth(), $this->jsonPayload()
        ), 201);
    }
}
