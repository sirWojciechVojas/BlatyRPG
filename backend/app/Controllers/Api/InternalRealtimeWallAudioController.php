<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Campaign\CampaignException;
use App\Services\Chat\CampaignChatException;
use App\Services\Realtime\RealtimePrincipalService;
use App\Services\Wall\WallAudioProjectionService;
use CodeIgniter\API\ResponseTrait;

class InternalRealtimeWallAudioController extends BaseController
{
    use ResponseTrait;

    private $principals;
    private $audio;

    public function __construct(
        ?RealtimePrincipalService $principals = null,
        ?WallAudioProjectionService $audio = null
    ) {
        $this->principals = $principals ?: new RealtimePrincipalService();
        $this->audio = $audio ?: new WallAudioProjectionService();
    }

    public function state($campaignId = null)
    {
        return $this->execute($campaignId, function (int $id, array $auth, array $payload): array {
            $this->exactKeys($payload, ['sceneId', 'selectedTokenId']);
            return $this->audio->state(
                $id,
                $this->positiveId($payload['sceneId'] ?? null),
                $auth,
                $this->nullableId($payload['selectedTokenId'] ?? null)
            );
        });
    }

    public function cue($campaignId = null)
    {
        return $this->execute($campaignId, function (int $id, array $auth, array $payload): array {
            $this->exactKeys($payload, ['sceneId', 'wallId', 'cue', 'selectedTokenId']);
            return $this->audio->cue(
                $id,
                $this->positiveId($payload['sceneId'] ?? null),
                $this->positiveId($payload['wallId'] ?? null),
                trim((string) ($payload['cue'] ?? '')),
                $auth,
                $this->nullableId($payload['selectedTokenId'] ?? null)
            );
        });
    }

    private function execute($campaignId, callable $operation)
    {
        try {
            $id = $this->positiveId($campaignId);
            $payload = $this->jsonPayload();
            $auth = $this->principals->resolve(
                $this->request->getHeaderLine('Authorization'),
                $this->request->getHeaderLine('X-Realtime-Client-Instance'),
                $id
            );
            return $this->respond($operation($id, $auth, $payload));
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

    private function exactKeys(array $payload, array $allowed): void
    {
        $unexpected = array_diff(array_keys($payload), $allowed);
        if ($unexpected) {
            throw new CampaignException('validation_failed', 'Wall audio request is invalid.', 422);
        }
    }

    private function positiveId($value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new CampaignException('not_found', 'Resource was not found.', 404);
        return (int) $id;
    }

    private function nullableId($value): ?int
    {
        if ($value === null || $value === '') return null;
        return $this->positiveId($value);
    }

    private function failure($exception)
    {
        $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
        if ($exception->details()) $payload['errors'] = $exception->details();
        return $this->response->setStatusCode($exception->status())->setJSON($payload);
    }
}

