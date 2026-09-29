<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Auth\AuthContextService;
use App\Services\MapBuilder\MapBuilderException;
use App\Services\MapBuilder\MapBuilderService;
use App\Services\Scene\SceneAssetService;
use App\Services\Scene\SceneException;
use CodeIgniter\API\ResponseTrait;

final class MapBuilderController extends BaseController
{
    use ResponseTrait;

    private $authContext;
    private $maps;
    private $sceneAssets;

    public function __construct()
    {
        $this->authContext = new AuthContextService();
        $this->maps = new MapBuilderService();
        $this->sceneAssets = new SceneAssetService();
    }

    public function index($campaignId = null)
    {
        return $this->execute(fn (): array => $this->maps->listProjects((int) $campaignId, $this->auth()));
    }

    public function show($campaignId = null, $mapId = null)
    {
        return $this->execute(fn (): array => $this->maps->getProject((int) $campaignId, (int) $mapId, $this->auth()));
    }

    public function create($campaignId = null)
    {
        return $this->execute(
            fn (): array => $this->maps->createProject((int) $campaignId, $this->auth(), $this->jsonPayload()),
            201
        );
    }

    public function save($campaignId = null, $mapId = null)
    {
        return $this->execute(fn (): array => $this->maps->saveProject(
            (int) $campaignId,
            (int) $mapId,
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function acquireLock($campaignId = null, $mapId = null)
    {
        return $this->execute(fn (): array => $this->maps->acquireLock(
            (int) $campaignId,
            (int) $mapId,
            $this->auth(),
            (string) ($this->jsonPayload()['editorId'] ?? '')
        ));
    }

    public function releaseLock($campaignId = null, $mapId = null)
    {
        return $this->execute(fn (): array => $this->maps->releaseLock(
            (int) $campaignId,
            (int) $mapId,
            $this->auth(),
            (string) ($this->jsonPayload()['editorId'] ?? '')
        ));
    }

    public function revisions($campaignId = null, $mapId = null)
    {
        return $this->execute(fn (): array => $this->maps->listRevisions(
            (int) $campaignId,
            (int) $mapId,
            $this->auth()
        ));
    }

    public function restore($campaignId = null, $mapId = null, $revisionId = null)
    {
        return $this->execute(fn (): array => $this->maps->restoreRevision(
            (int) $campaignId,
            (int) $mapId,
            (int) $revisionId,
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function publish($campaignId = null, $mapId = null)
    {
        return $this->execute(fn (): array => $this->maps->publish(
            (int) $campaignId,
            (int) $mapId,
            $this->auth(),
            $this->jsonPayload()
        ));
    }

    public function assets($campaignId = null)
    {
        return $this->execute(fn (): array => $this->maps->listAssets((int) $campaignId, $this->auth()));
    }

    public function uploadAsset($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            $metadataRaw = (string) $this->request->getPost('metadata');
            $metadata = $metadataRaw !== '' ? json_decode($metadataRaw, true) : [];
            if (!is_array($metadata)) {
                throw new MapBuilderException('validation_failed', 'Asset metadata must be valid JSON.', 422);
            }
            return $this->maps->uploadAsset(
                (int) $campaignId,
                $this->auth(),
                $this->request->getFile('file'),
                $metadata
            );
        }, 201);
    }

    public function importPackage($campaignId = null)
    {
        return $this->execute(fn (): array => $this->maps->importPackage(
            (int) $campaignId,
            $this->auth(),
            $this->request->getFile('file')
        ), 201);
    }

    public function assetFile($campaignId = null, $assetId = null)
    {
        try {
            $result = $this->maps->downloadAsset((int) $campaignId, (int) $assetId, $this->auth());
            $asset = $result['asset'];
            if (!empty($result['url'])) {
                return $this->response->setStatusCode(302)->setHeader('Location', (string) $result['url'])
                    ->setHeader('Cache-Control', 'private, no-store');
            }
            return $this->response
                ->setHeader('Content-Type', (string) $asset['mimeType'])
                ->setHeader('Content-Length', (string) filesize($result['path']))
                ->setHeader('Cache-Control', 'private, max-age=31536000, immutable')
                ->setHeader('Content-Disposition', 'inline; filename="map-asset-' . (int) $assetId . '"')
                ->setBody((string) file_get_contents($result['path']));
        } catch (MapBuilderException $exception) {
            return $this->exception($exception);
        }
    }

    public function uploadRender($campaignId = null, $mapId = null)
    {
        return $this->execute(function () use ($campaignId, $mapId): array {
            // getProject is an authorization and ownership boundary before using scene storage.
            $this->maps->getProject((int) $campaignId, (int) $mapId, $this->auth());
            return $this->sceneAssets->upload(
                (int) $campaignId,
                $this->auth(),
                $this->request->getFile('file')
            );
        }, 201);
    }

    public function aiStatus($campaignId = null)
    {
        return $this->execute(fn (): array => $this->maps->aiStatus((int) $campaignId, $this->auth()));
    }

    public function createAiJob($campaignId = null, $mapId = null)
    {
        return $this->execute(fn (): array => $this->maps->createAiJob(
            (int) $campaignId,
            (int) $mapId,
            $this->auth(),
            $this->jsonPayload()
        ), 202);
    }

    public function aiJob($campaignId = null, $mapId = null, $jobId = null)
    {
        return $this->execute(fn (): array => $this->maps->getAiJob(
            (int) $campaignId,
            (int) $mapId,
            (string) $jobId,
            $this->auth()
        ));
    }

    public function cancelAiJob($campaignId = null, $mapId = null, $jobId = null)
    {
        return $this->execute(fn (): array => $this->maps->cancelAiJob(
            (int) $campaignId,
            (int) $mapId,
            (string) $jobId,
            $this->auth()
        ));
    }

    private function auth(): array
    {
        return $this->authContext->resolveFromRequest($this->request);
    }

    private function jsonPayload(): array
    {
        try {
            $payload = $this->request->getJSON(true);
        } catch (\Throwable $exception) {
            throw new MapBuilderException('invalid_json', 'Request body must contain valid JSON.', 400);
        }
        if (!is_array($payload)) {
            throw new MapBuilderException('invalid_json', 'Request body must be a JSON object.', 400);
        }
        return $payload;
    }

    private function execute(callable $operation, int $status = 200)
    {
        try {
            return $this->respond($operation(), $status);
        } catch (MapBuilderException $exception) {
            return $this->exception($exception);
        } catch (SceneException $exception) {
            return $this->response->setStatusCode($exception->status())->setJSON([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'errors' => $exception->details(),
            ]);
        }
    }

    private function exception(MapBuilderException $exception)
    {
        $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
        if ($exception->details()) $payload['errors'] = $exception->details();
        if (isset($exception->details()['lock'])) $payload['lock'] = $exception->details()['lock'];
        return $this->response->setStatusCode($exception->status())->setJSON($payload);
    }
}
