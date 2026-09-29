<?php

namespace App\Controllers\Api;

use App\Services\Campaign\CampaignException;
use App\Services\Profession\ProfessionCatalogService;

final class ProfessionController extends CampaignApiController
{
    private $professions;

    public function __construct()
    {
        parent::__construct();
        $this->professions = new ProfessionCatalogService();
    }

    public function campaign($campaignId = null)
    {
        return $this->execute(fn (): array => $this->professions->campaignCatalog(
            $this->positiveId($campaignId, 'campaign_not_found'),
            $this->auth()
        ));
    }

    public function system($systemId = null)
    {
        return $this->execute(fn (): array => $this->professions->systemCatalog(
            $this->positiveId($systemId, 'rpg_system_not_found')
        ));
    }

    public function character($campaignId = null, $characterId = null)
    {
        return $this->execute(fn (): array => $this->professions->characterHistory(
            $this->positiveId($campaignId, 'campaign_not_found'),
            $this->positiveId($characterId, 'character_not_found'),
            $this->auth()
        ));
    }

    public function changeCharacterProfession($campaignId = null, $characterId = null)
    {
        return $this->execute(fn (): array => $this->professions
            ->changeCharacterProfession(
                $this->positiveId($campaignId, 'campaign_not_found'),
                $this->positiveId($characterId, 'character_not_found'),
                $this->auth(),
                $this->jsonPayload()
            ));
    }

    public function reorderCharacterProfessions($campaignId = null, $characterId = null)
    {
        return $this->execute(fn (): array => $this->professions
            ->reorderCharacterProfessions(
                $this->positiveId($campaignId, 'campaign_not_found'),
                $this->positiveId($characterId, 'character_not_found'),
                $this->auth(),
                $this->jsonPayload()
            ));
    }

    public function activateCharacterProfession(
        $campaignId = null,
        $characterId = null,
        $historyId = null
    ) {
        return $this->execute(fn (): array => $this->professions
            ->activateCharacterProfession(
                $this->positiveId($campaignId, 'campaign_not_found'),
                $this->positiveId($characterId, 'character_not_found'),
                $this->positiveId($historyId, 'profession_history_not_found'),
                $this->auth()
            ));
    }

    public function deleteCharacterProfession(
        $campaignId = null,
        $characterId = null,
        $historyId = null
    ) {
        return $this->execute(fn (): array => $this->professions
            ->deleteCharacterProfession(
                $this->positiveId($campaignId, 'campaign_not_found'),
                $this->positiveId($characterId, 'character_not_found'),
                $this->positiveId($historyId, 'profession_history_not_found'),
                $this->auth()
            ));
    }

    public function assetFile($assetId = null)
    {
        try {
            $result = $this->professions->asset(
                $this->positiveId($assetId, 'profession_asset_not_found'),
                $this->auth()
            );
            $asset = $result['asset'];
            if (!empty($result['url'])) {
                return $this->response->setStatusCode(302)->setHeader('Location', (string) $result['url'])
                    ->setHeader('Cache-Control', 'private, no-store');
            }
            return $this->response
                ->setHeader('Content-Type', (string) $asset['mime_type'])
                ->setHeader('Content-Length', (string) filesize($result['path']))
                ->setHeader('Cache-Control', 'private, max-age=3600')
                ->setHeader(
                    'Content-Disposition',
                    'inline; filename="' . addcslashes(
                        (string) $asset['original_name'],
                        "\\\""
                    ) . '"'
                )
                ->setBody((string) file_get_contents($result['path']));
        } catch (CampaignException $exception) {
            $payload = [
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ];
            if ($exception->details()) $payload['errors'] = $exception->details();
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }
}
