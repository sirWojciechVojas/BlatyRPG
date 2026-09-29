<?php

namespace App\Controllers\Api;

use App\Services\Magic\HeroMagicService;

final class HeroMagicController extends CampaignApiController
{
    private $magic;

    public function __construct()
    {
        parent::__construct();
        $this->magic = new HeroMagicService();
    }

    public function show($campaignId = null, $characterId = null)
    {
        return $this->execute(fn (): array => $this->magic->overview(
            $this->positiveId($campaignId),
            $this->positiveId($characterId, 'character_not_found'),
            $this->auth()
        ));
    }

    public function preferences($campaignId = null, $characterId = null)
    {
        return $this->execute(fn (): array => $this->magic->updatePreferences(
            $this->positiveId($campaignId),
            $this->positiveId($characterId, 'character_not_found'),
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function reveal($campaignId = null, $characterId = null, $spellId = null)
    {
        return $this->execute(fn (): array => $this->magic->revealSpell(
            $this->positiveId($campaignId),
            $this->positiveId($characterId, 'character_not_found'),
            $this->positiveId($spellId, 'spell_not_found'),
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function profile($campaignId = null, $characterId = null)
    {
        return $this->execute(fn (): array => $this->magic->configureProfile(
            $this->positiveId($campaignId),
            $this->positiveId($characterId, 'character_not_found'),
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function learn($campaignId = null, $characterId = null)
    {
        return $this->execute(fn (): array => $this->magic->requestLearning(
            $this->positiveId($campaignId),
            $this->positiveId($characterId, 'character_not_found'),
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function decideLearning($campaignId = null, $requestId = null)
    {
        return $this->execute(fn (): array => $this->magic->decideLearning(
            $this->positiveId($campaignId),
            $this->positiveId($requestId, 'learning_request_not_found'),
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function createCast($campaignId = null, $characterId = null)
    {
        return $this->execute(fn (): array => $this->magic->createCast(
            $this->positiveId($campaignId),
            $this->positiveId($characterId, 'character_not_found'),
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function channel($campaignId = null, $castId = null)
    {
        return $this->execute(fn (): array => $this->magic->channel(
            $this->positiveId($campaignId),
            $this->positiveId($castId, 'cast_not_found'),
            $this->auth()
        ));
    }

    public function advanceCast($campaignId = null, $castId = null)
    {
        return $this->execute(fn (): array => $this->magic->advanceCast(
            $this->positiveId($campaignId),
            $this->positiveId($castId, 'cast_not_found'),
            $this->auth()
        ));
    }

    public function resolveCast($campaignId = null, $castId = null)
    {
        return $this->execute(fn (): array => $this->magic->resolveCast(
            $this->positiveId($campaignId),
            $this->positiveId($castId, 'cast_not_found'),
            $this->auth()
        ));
    }

    public function cancelCast($campaignId = null, $castId = null)
    {
        return $this->execute(fn (): array => $this->magic->cancelCast(
            $this->positiveId($campaignId),
            $this->positiveId($castId, 'cast_not_found'),
            $this->auth()
        ));
    }

    public function ritual($campaignId = null, $characterId = null)
    {
        return $this->execute(fn (): array => $this->magic->createRitual(
            $this->positiveId($campaignId),
            $this->positiveId($characterId, 'character_not_found'),
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function history($campaignId = null, $characterId = null)
    {
        return $this->execute(fn (): array => $this->magic->history(
            $this->positiveId($campaignId),
            $this->positiveId($characterId, 'character_not_found'),
            $this->auth(),
            (int) ($this->request->getGet('limit') ?? 100)
        ));
    }
}
