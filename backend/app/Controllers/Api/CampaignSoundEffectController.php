<?php

namespace App\Controllers\Api;

use App\Services\Audio\AudioLibraryService;
use App\Services\Audio\SoundEffectService;

class CampaignSoundEffectController extends CampaignApiController
{
    private $effects;
    private $audio;

    public function __construct()
    {
        parent::__construct();
        $this->effects = new SoundEffectService();
        $this->audio = new AudioLibraryService();
    }

    public function index($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            $auth = $this->auth();
            $id = $this->positiveId($campaignId, 'campaign_not_found');
            return $this->effects->snapshot($auth, $id) + ['library' => $this->audio->list($auth, $id)];
        });
    }

    public function createScreen($campaignId = null)
    {
        return $this->execute(fn (): array => $this->effects->createScreen(
            $this->auth(), $this->positiveId($campaignId, 'campaign_not_found'), $this->jsonPayload()
        ), 201);
    }

    public function updateScreen($campaignId = null, $screenId = null)
    {
        return $this->execute(fn (): array => $this->effects->updateScreen(
            $this->auth(), $this->positiveId($campaignId, 'campaign_not_found'),
            $this->positiveId($screenId, 'sound_effect_screen_not_found'), $this->jsonPayload()
        ));
    }

    public function duplicateScreen($campaignId = null, $screenId = null)
    {
        return $this->execute(fn (): array => $this->effects->duplicateScreen(
            $this->auth(), $this->positiveId($campaignId, 'campaign_not_found'),
            $this->positiveId($screenId, 'sound_effect_screen_not_found')
        ), 201);
    }

    public function deleteScreen($campaignId = null, $screenId = null)
    {
        return $this->execute(fn (): array => $this->effects->deleteScreen(
            $this->auth(), $this->positiveId($campaignId, 'campaign_not_found'),
            $this->positiveId($screenId, 'sound_effect_screen_not_found')
        ));
    }

    public function saveSlot($campaignId = null, $screenId = null, $position = null)
    {
        return $this->execute(fn (): array => $this->effects->saveSlot(
            $this->auth(), $this->positiveId($campaignId, 'campaign_not_found'),
            $this->positiveId($screenId, 'sound_effect_screen_not_found'),
            $this->slotPosition($position), $this->jsonPayload()
        ));
    }

    public function deleteSlot($campaignId = null, $screenId = null, $position = null)
    {
        return $this->execute(fn (): array => $this->effects->deleteSlot(
            $this->auth(), $this->positiveId($campaignId, 'campaign_not_found'),
            $this->positiveId($screenId, 'sound_effect_screen_not_found'),
            $this->slotPosition($position)
        ));
    }

    private function slotPosition($value): int
    {
        $position = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 199]]);
        if ($position === false) {
            throw new \App\Services\Campaign\CampaignException('validation_failed', 'Sound effect slot position is invalid.', 422);
        }
        return (int) $position;
    }
}
