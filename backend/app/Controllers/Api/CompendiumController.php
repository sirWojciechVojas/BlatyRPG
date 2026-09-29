<?php

namespace App\Controllers\Api;

use App\Services\Campaign\CampaignException;
use App\Services\Compendium\CompendiumAssetService;
use App\Services\Compendium\CompendiumService;

final class CompendiumController extends CampaignApiController
{
    private $compendium;
    private $assets;

    public function __construct()
    {
        parent::__construct();
        $this->compendium = new CompendiumService();
        $this->assets = new CompendiumAssetService();
    }

    public function mine()
    {
        return $this->execute(fn (): array => $this->compendium->mine($this->auth()));
    }

    public function overview($universeId = null)
    {
        return $this->execute(fn (): array => $this->compendium->overview($this->positiveId($universeId), $this->auth()));
    }

    public function index($universeId = null)
    {
        return $this->execute(fn (): array => $this->compendium->editorialIndex(
            $this->positiveId($universeId), $this->auth(), $this->request->getGet()
        ));
    }

    public function show($universeId = null, $entryId = null)
    {
        return $this->execute(fn (): array => $this->compendium->editorialShow(
            $this->positiveId($universeId), $this->positiveId($entryId, 'compendium_entry_not_found'), $this->auth()
        ));
    }

    public function create($universeId = null)
    {
        return $this->execute(fn (): array => $this->compendium->createEntry(
            $this->positiveId($universeId), $this->auth(), $this->jsonPayload()
        ), 201);
    }

    public function update($universeId = null, $entryId = null)
    {
        return $this->execute(fn (): array => $this->compendium->updateEntry(
            $this->positiveId($universeId), $this->positiveId($entryId), $this->auth(), $this->jsonPayload()
        ));
    }

    public function publish($universeId = null, $entryId = null)
    {
        return $this->execute(fn (): array => $this->compendium->publish(
            $this->positiveId($universeId), $this->positiveId($entryId), $this->auth(), $this->jsonPayload()
        ));
    }

    public function restoreVersion($universeId = null, $entryId = null, $versionId = null)
    {
        return $this->execute(fn (): array => $this->compendium->restoreVersion(
            $this->positiveId($universeId), $this->positiveId($entryId), $this->positiveId($versionId),
            $this->auth(), $this->jsonPayload()
        ));
    }

    public function archive($universeId = null, $entryId = null)
    {
        return $this->execute(fn (): array => $this->compendium->archive(
            $this->positiveId($universeId), $this->positiveId($entryId), $this->auth(), $this->jsonPayload(), false
        ));
    }

    public function restore($universeId = null, $entryId = null)
    {
        return $this->execute(fn (): array => $this->compendium->archive(
            $this->positiveId($universeId), $this->positiveId($entryId), $this->auth(), $this->jsonPayload(), true
        ));
    }

    public function createTag($universeId = null)
    {
        return $this->execute(fn (): array => $this->compendium->createTag($this->positiveId($universeId), $this->auth(), $this->jsonPayload()), 201);
    }

    public function deleteTag($universeId = null, $tagId = null)
    {
        return $this->execute(fn (): array => $this->compendium->deleteTag($this->positiveId($universeId), $this->positiveId($tagId), $this->auth()));
    }

    public function createType($universeId = null)
    {
        return $this->execute(fn (): array => $this->compendium->createType($this->positiveId($universeId), $this->auth(), $this->jsonPayload()), 201);
    }

    public function updateType($universeId = null, $typeId = null)
    {
        return $this->execute(fn (): array => $this->compendium->updateType($this->positiveId($universeId), $this->positiveId($typeId), $this->auth(), $this->jsonPayload()));
    }

    public function deleteType($universeId = null, $typeId = null)
    {
        return $this->execute(fn (): array => $this->compendium->deleteType($this->positiveId($universeId), $this->positiveId($typeId), $this->auth()));
    }

    public function updateCalendar($universeId = null)
    {
        return $this->execute(fn (): array => $this->compendium->updateCalendar($this->positiveId($universeId), $this->auth(), $this->jsonPayload()));
    }

    public function assignOwner($universeId = null)
    {
        return $this->execute(fn (): array => $this->compendium->assignOwner($this->positiveId($universeId), $this->auth(), $this->jsonPayload()));
    }

    public function addEditor($universeId = null)
    {
        return $this->execute(fn (): array => $this->compendium->addEditor($this->positiveId($universeId), $this->auth(), $this->jsonPayload()), 201);
    }

    public function removeEditor($universeId = null, $userId = null)
    {
        return $this->execute(fn (): array => $this->compendium->removeEditor($this->positiveId($universeId), $this->positiveId($userId), $this->auth()));
    }

    public function uploadAsset($universeId = null)
    {
        return $this->execute(fn (): array => $this->assets->upload(
            $this->positiveId($universeId), $this->auth(), $this->request->getFile('file')
        ), 201);
    }

    public function assets($universeId = null)
    {
        return $this->execute(fn (): array => $this->assets->index($this->positiveId($universeId), $this->auth()));
    }

    public function deleteAsset($universeId = null, $assetId = null)
    {
        return $this->execute(fn (): array => $this->assets->delete(
            $this->positiveId($universeId), $this->positiveId($assetId, 'compendium_asset_not_found'), $this->auth()
        ));
    }

    public function assetFile($assetId = null)
    {
        try {
            $campaignId = $this->request->getGet('campaignId');
            $result = $this->assets->download($this->positiveId($assetId), $this->auth(),
                $campaignId ? $this->positiveId($campaignId) : null);
            $asset = $result['asset'];
            return $this->response->setHeader('Content-Type', (string) $asset['mime_type'])
                ->setHeader('Content-Length', (string) filesize($result['path']))
                ->setHeader('Content-Disposition', 'inline; filename="' . addcslashes((string) $asset['original_name'], "\\\"") . '"')
                ->setHeader('Cache-Control', 'private, max-age=300')
                ->setBody((string) file_get_contents($result['path']));
        } catch (CampaignException $exception) {
            return $this->response->setStatusCode($exception->status())->setJSON([
                'code' => $exception->errorCode(), 'message' => $exception->getMessage(),
            ]);
        }
    }
}
