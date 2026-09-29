<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Auth\AuthContextService;
use App\Services\Scene\SceneException;
use App\Services\Scene\SceneAssetService;
use App\Services\Scene\SceneService;
use CodeIgniter\API\ResponseTrait;

class SceneController extends BaseController
{
    use ResponseTrait;

    private $authContext;
    private $scenes;
    private $assets;

    public function __construct()
    {
        $this->authContext = new AuthContextService();
        $this->scenes = new SceneService();
        $this->assets = new SceneAssetService();
    }

    public function assets($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->assets->list((int) $campaignId, $this->auth());
        });
    }

    public function uploadAsset($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->assets->upload(
                (int) $campaignId,
                $this->auth(),
                $this->request->getFile('file')
            );
        }, 201);
    }

    public function assetFile($campaignId = null, $assetKey = null)
    {
        try {
            $result = $this->assets->download(
                (int) $campaignId,
                rawurldecode((string) $assetKey),
                $this->auth()
            );
            if (!empty($result['url'])) {
                return $this->response->setStatusCode(302)->setHeader('Location', (string) $result['url'])
                    ->setHeader('Cache-Control', 'private, no-store');
            }
            $asset = $result['asset'];
            return $this->response
                ->setHeader('Content-Type', (string) $asset['mimeType'])
                ->setHeader('Content-Length', (string) filesize($result['path']))
                ->setHeader(
                    'Content-Disposition',
                    'inline; filename="' . addcslashes((string) $asset['name'], "\\\"") . '"'
                )
                ->setBody((string) file_get_contents($result['path']));
        } catch (SceneException $exception) {
            $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
            if ($exception->details()) {
                $payload['errors'] = $exception->details();
            }
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }

    public function index($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->scenes->listScenes((int) $campaignId, $this->auth());
        });
    }

    public function show($campaignId = null, $sceneId = null)
    {
        return $this->execute(function () use ($campaignId, $sceneId): array {
            return $this->scenes->getScene((int) $campaignId, (int) $sceneId, $this->auth());
        });
    }

    public function create($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->scenes->createScene((int) $campaignId, $this->auth(), $this->jsonPayload());
        }, 201);
    }

    public function duplicate($campaignId = null, $sceneId = null)
    {
        return $this->execute(function () use ($campaignId, $sceneId): array {
            return $this->scenes->duplicateScene(
                (int) $campaignId,
                (int) $sceneId,
                $this->auth(),
                $this->jsonPayload()
            );
        }, 201);
    }

    public function update($campaignId = null, $sceneId = null)
    {
        return $this->execute(function () use ($campaignId, $sceneId): array {
            return $this->scenes->updateScene(
                (int) $campaignId,
                (int) $sceneId,
                $this->auth(),
                $this->jsonPayload()
            );
        });
    }

    public function delete($campaignId = null, $sceneId = null)
    {
        return $this->execute(function () use ($campaignId, $sceneId): array {
            return $this->scenes->deleteScene(
                (int) $campaignId,
                (int) $sceneId,
                $this->auth(),
                $this->jsonPayload()
            );
        });
    }

    public function activate($campaignId = null, $sceneId = null)
    {
        return $this->execute(function () use ($campaignId, $sceneId): array {
            return $this->scenes->activateScene(
                (int) $campaignId,
                (int) $sceneId,
                $this->auth(),
                $this->jsonPayload()
            );
        });
    }

    public function transitionDarkness($campaignId = null, $sceneId = null)
    {
        return $this->execute(function () use ($campaignId, $sceneId): array {
            return $this->scenes->transitionDarkness(
                (int) $campaignId,
                (int) $sceneId,
                $this->auth(),
                $this->jsonPayload()
            );
        });
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
            throw new SceneException('invalid_json', 'Request body must contain valid JSON.', 400);
        }
        if (!is_array($payload)) {
            throw new SceneException('invalid_json', 'Request body must be a JSON object.', 400);
        }
        return $payload;
    }

    private function execute(callable $operation, int $successStatus = 200)
    {
        try {
            return $this->respond($operation(), $successStatus);
        } catch (SceneException $exception) {
            $payload = [
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ];
            if ($exception->details()) {
                $payload['errors'] = $exception->details();
            }
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }
}
