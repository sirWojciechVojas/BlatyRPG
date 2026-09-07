<?php

namespace App\Controllers\Api;

use App\Services\Campaign\CampaignException;
use App\Services\Handout\HandoutService;

class HandoutLibraryController extends CampaignApiController
{
    private $handouts;

    public function __construct()
    {
        parent::__construct();
        $this->handouts = new HandoutService();
    }

    public function index()
    {
        return $this->execute(function (): array {
            return $this->handouts->listLibrary($this->auth(), $this->request->getGet());
        });
    }

    public function show($entryId = null)
    {
        return $this->execute(function () use ($entryId): array {
            return $this->handouts->getLibraryEntry($this->auth(), $this->positiveId($entryId, 'handout_library_entry_not_found'));
        });
    }

    public function createEntry()
    {
        return $this->execute(function (): array {
            return $this->handouts->createLibraryEntry($this->auth(), $this->jsonPayload());
        }, 201);
    }

    public function updateEntry($entryId = null)
    {
        return $this->execute(function () use ($entryId): array {
            return $this->handouts->updateLibraryEntry($this->auth(), $this->positiveId($entryId, 'handout_library_entry_not_found'), $this->jsonPayload());
        });
    }

    public function deleteEntry($entryId = null)
    {
        return $this->execute(function () use ($entryId): array {
            return $this->handouts->trashLibraryEntry($this->auth(), $this->positiveId($entryId, 'handout_library_entry_not_found'), $this->jsonPayload());
        });
    }

    public function restoreEntry($entryId = null)
    {
        return $this->execute(function () use ($entryId): array {
            return $this->handouts->restoreLibraryEntry($this->auth(), $this->positiveId($entryId, 'handout_library_entry_not_found'));
        });
    }

    public function createFolder()
    {
        return $this->execute(function (): array { return $this->handouts->createFolder($this->auth(), $this->jsonPayload()); }, 201);
    }

    public function updateFolder($folderId = null)
    {
        return $this->execute(function () use ($folderId): array {
            return $this->handouts->updateFolder($this->auth(), $this->positiveId($folderId, 'handout_folder_not_found'), $this->jsonPayload());
        });
    }

    public function deleteFolder($folderId = null)
    {
        return $this->execute(function () use ($folderId): array {
            return $this->handouts->trashFolder($this->auth(), $this->positiveId($folderId, 'handout_folder_not_found'));
        });
    }

    public function createTag()
    {
        return $this->execute(function (): array { return $this->handouts->createTag($this->auth(), $this->jsonPayload()); }, 201);
    }

    public function deleteTag($tagId = null)
    {
        return $this->execute(function () use ($tagId): array {
            return $this->handouts->deleteTag($this->auth(), $this->positiveId($tagId, 'handout_tag_not_found'));
        });
    }

    public function uploadAsset()
    {
        return $this->execute(function (): array {
            return $this->handouts->uploadAsset($this->auth(), $this->request->getFile('file'));
        }, 201);
    }

    public function assetFile($assetId = null)
    {
        try {
            $result = $this->handouts->assetForDownload($this->auth(), $this->positiveId($assetId, 'handout_asset_not_found'));
            $asset = $result['asset'];
            return $this->response->setHeader('Content-Type', (string) $asset['mime_type'])
                ->setHeader('Content-Length', (string) filesize($result['path']))
                ->setHeader('Content-Disposition', 'inline; filename="' . addcslashes((string) $asset['original_name'], "\\\"") . '"')
                ->setBody((string) file_get_contents($result['path']));
        } catch (CampaignException $exception) {
            $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
            if ($exception->details()) $payload['errors'] = $exception->details();
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }
}
