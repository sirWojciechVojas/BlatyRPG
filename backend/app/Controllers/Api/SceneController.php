<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Auth\AuthContextService;
use App\Services\Scene\SceneException;
use App\Services\Scene\SceneService;
use CodeIgniter\API\ResponseTrait;

class SceneController extends BaseController
{
    use ResponseTrait;

    private $authContext;
    private $scenes;

    public function __construct()
    {
        $this->authContext = new AuthContextService();
        $this->scenes = new SceneService();
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
