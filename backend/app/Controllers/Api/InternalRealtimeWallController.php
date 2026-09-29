<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Campaign\CampaignException;
use App\Services\Chat\CampaignChatException;
use App\Services\Realtime\RealtimePrincipalService;
use App\Services\Wall\SceneWallService;
use CodeIgniter\API\ResponseTrait;

/** Docker-network adapter; SceneWallService remains the authority. */
class InternalRealtimeWallController extends BaseController
{
    use ResponseTrait;

    private $principals;
    private $walls;

    public function __construct(
        ?RealtimePrincipalService $principals = null,
        ?SceneWallService $walls = null
    ) {
        $this->principals = $principals ?: new RealtimePrincipalService();
        $this->walls = $walls ?: new SceneWallService();
    }

    public function change($campaignId = null)
    {
        try {
            $id = $this->positiveId($campaignId);
            $payload = $this->jsonPayload();
            $operation = strtolower(trim((string) ($payload['operation'] ?? '')));
            $this->exactKeys($payload, $operation);
            $sceneId = $this->positiveId($payload['sceneId'] ?? null);
            $auth = $this->principals->resolve(
                $this->request->getHeaderLine('Authorization'),
                $this->request->getHeaderLine('X-Realtime-Client-Instance'),
                $id
            );
            if ($operation === 'create') {
                return $this->respond($this->walls->create(
                    $id,
                    $sceneId,
                    $auth,
                    $this->changes($payload)
                ), 201);
            }
            $wallId = $this->positiveId($payload['wallId'] ?? null);
            $revision = $payload['revision'] ?? null;
            if ($operation === 'update') {
                return $this->respond($this->walls->update(
                    $id,
                    $sceneId,
                    $wallId,
                    $auth,
                    array_merge($this->changes($payload), ['revision' => $revision])
                ));
            }
            if ($operation === 'interact') {
                $changes = $this->changes($payload);
                return $this->respond($this->walls->interact(
                    $id,
                    $sceneId,
                    $wallId,
                    $auth,
                    [
                        'doorState' => $changes['doorState'] ?? null,
                        'actingTokenIds' => $changes['actingTokenIds'] ?? [],
                        'silent' => $changes['silent'] ?? false,
                        'revision' => $revision,
                    ]
                ));
            }
            if ($operation === 'delete') {
                return $this->respond($this->walls->delete(
                    $id,
                    $sceneId,
                    $wallId,
                    $auth,
                    ['revision' => $revision]
                ));
            }
            throw new CampaignException('validation_failed', 'Wall operation is invalid.', 422);
        } catch (CampaignException $exception) {
            return $this->failure($exception);
        } catch (CampaignChatException $exception) {
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
            throw new CampaignException('validation_failed', 'Wall changes are required.', 422);
        }
        return $payload['changes'];
    }

    private function exactKeys(array $payload, string $operation): void
    {
        $allowed = $operation === 'create'
            ? ['operation', 'sceneId', 'changes']
            : ['operation', 'sceneId', 'wallId', 'revision', 'changes'];
        if ($operation === 'delete') {
            $allowed = ['operation', 'sceneId', 'wallId', 'revision'];
        }
        $unexpected = array_diff(array_keys($payload), $allowed);
        if ($unexpected) {
            throw new CampaignException(
                'validation_failed',
                'Wall operation is invalid.',
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
