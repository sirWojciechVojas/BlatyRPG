<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Campaign\CampaignException;
use App\Services\Chat\CampaignChatException;
use App\Services\Light\LightException;
use App\Services\Light\SceneLightService;
use App\Services\Realtime\RealtimePrincipalService;
use App\Services\Scene\SceneService;
use CodeIgniter\API\ResponseTrait;

/** Docker-network adapter; SceneLightService remains the authority. */
class InternalRealtimeLightController extends BaseController
{
    use ResponseTrait;

    private $principals;
    private $lights;
    private $scenes;

    public function __construct(
        ?RealtimePrincipalService $principals = null,
        ?SceneLightService $lights = null,
        ?SceneService $scenes = null
    ) {
        $this->principals = $principals ?: new RealtimePrincipalService();
        $this->lights = $lights ?: new SceneLightService();
        $this->scenes = $scenes ?: new SceneService();
    }

    public function change($campaignId = null)
    {
        try {
            $id = $this->positiveId($campaignId);
            $payload = $this->jsonPayload();
            $operation = $this->operation($payload['operation'] ?? '');
            $this->exactKeys($payload, $operation);
            $sceneId = $this->positiveId($payload['sceneId'] ?? null);
            $auth = $this->principals->resolve(
                $this->request->getHeaderLine('Authorization'),
                $this->request->getHeaderLine('X-Realtime-Client-Instance'),
                $id
            );
            if ($operation === 'syncScene') {
                return $this->respond($this->scenes->getScene($id, $sceneId, $auth));
            }
            if ($operation === 'create') {
                return $this->respond($this->lights->create(
                    $id,
                    $sceneId,
                    $auth,
                    $this->changes($payload)
                ), 201);
            }
            $lightId = $this->positiveId($payload['lightId'] ?? null);
            $revision = $payload['revision'] ?? null;
            if ($operation === 'update') {
                return $this->respond($this->lights->update(
                    $id,
                    $sceneId,
                    $lightId,
                    $auth,
                    array_merge($this->changes($payload), ['revision' => $revision])
                ));
            }
            if ($operation === 'delete') {
                return $this->respond($this->lights->delete(
                    $id,
                    $sceneId,
                    $lightId,
                    $auth,
                    ['revision' => $revision]
                ));
            }
            throw new CampaignException('validation_failed', 'Light operation is invalid.', 422);
        } catch (CampaignException | CampaignChatException | LightException $exception) {
            return $this->failure($exception);
        }
    }

    private function jsonPayload(): array
    {
        try {
            $payload = $this->request->getJSON(true);
        } catch (\Throwable $exception) {
            throw new CampaignException('invalid_json', 'Valid JSON is required.', 400);
        }
        if (!is_array($payload)) {
            throw new CampaignException('invalid_json', 'A JSON object is required.', 400);
        }
        return $payload;
    }

    private function changes(array $payload): array
    {
        if (!isset($payload['changes']) || !is_array($payload['changes'])) {
            throw new CampaignException('validation_failed', 'Light changes are required.', 422);
        }
        return $payload['changes'];
    }

    private function operation($value): string
    {
        $operation = strtolower(trim((string) $value));

        return $operation === 'syncscene' ? 'syncScene' : $operation;
    }

    private function exactKeys(array $payload, string $operation): void
    {
        if ($operation === 'syncScene') {
            $allowed = ['operation', 'sceneId'];
        } else {
            $allowed = $operation === 'create'
                ? ['operation', 'sceneId', 'changes']
                : ['operation', 'sceneId', 'lightId', 'revision', 'changes'];
            if ($operation === 'delete') {
                $allowed = ['operation', 'sceneId', 'lightId', 'revision'];
            }
        }
        $unexpected = array_diff(array_keys($payload), $allowed);
        if ($unexpected) {
            throw new CampaignException(
                'validation_failed',
                'Light operation is invalid.',
                422,
                array_fill_keys($unexpected, 'This field is not accepted.')
            );
        }
    }

    private function positiveId($value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new CampaignException('not_found', 'Resource was not found.', 404);
        return (int) $id;
    }

    private function failure($exception)
    {
        $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
        if ($exception->details()) $payload['errors'] = $exception->details();
        return $this->response->setStatusCode($exception->status())->setJSON($payload);
    }
}
