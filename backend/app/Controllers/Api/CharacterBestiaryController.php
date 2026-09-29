<?php

namespace App\Controllers\Api;

use App\Services\Bestiary\CharacterBestiaryService;

final class CharacterBestiaryController extends CampaignApiController
{
    private $bestiary;

    public function __construct()
    {
        parent::__construct();
        $this->bestiary = new CharacterBestiaryService();
    }

    public function index($campaignId = null, $characterId = null)
    {
        return $this->execute(fn (): array => $this->bestiary->index(
            $this->positiveId($campaignId),
            $this->positiveId($characterId, 'character_not_found'),
            $this->auth()
        ));
    }

    public function show(
        $campaignId = null,
        $characterId = null,
        $entryId = null
    ) {
        return $this->execute(fn (): array => $this->bestiary->show(
            $this->positiveId($campaignId),
            $this->positiveId($characterId, 'character_not_found'),
            $this->positiveId($entryId, 'bestiary_entry_not_found'),
            $this->auth()
        ));
    }

    public function assignments($campaignId = null, $entryId = null)
    {
        return $this->execute(fn (): array => $this->bestiary->assignments(
            $this->positiveId($campaignId),
            $this->positiveId($entryId, 'bestiary_entry_not_found'),
            $this->auth()
        ));
    }

    public function setAssignments($campaignId = null, $entryId = null)
    {
        return $this->execute(fn (): array => $this->bestiary->setAssignments(
            $this->positiveId($campaignId),
            $this->positiveId($entryId, 'bestiary_entry_not_found'),
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function setAssignment(
        $campaignId = null,
        $entryId = null,
        $characterId = null
    ) {
        return $this->execute(fn (): array => $this->bestiary->setAssignment(
            $this->positiveId($campaignId),
            $this->positiveId($entryId, 'bestiary_entry_not_found'),
            $this->positiveId($characterId, 'character_not_found'),
            $this->auth(),
            $this->jsonPayload()
        ));
    }
}
