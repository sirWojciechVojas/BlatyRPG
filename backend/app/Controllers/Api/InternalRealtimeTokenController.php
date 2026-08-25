<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Campaign\CampaignException;
use App\Services\Chat\CampaignChatException;
use App\Services\Realtime\RealtimePrincipalService;
use App\Services\Token\SceneTokenService;
use CodeIgniter\API\ResponseTrait;

/** Docker-network adapter; SceneTokenService remains the authority. */
class InternalRealtimeTokenController extends BaseController
{
    use ResponseTrait;

    private $principals;
    private $tokens;

    public function __construct(
        ?RealtimePrincipalService $principals = null,
        ?SceneTokenService $tokens = null
    ) {
        $this->principals = $principals ?: new RealtimePrincipalService();
        $this->tokens = $tokens ?: new SceneTokenService();
    }

    public function move($campaignId = null)
    {
        try {
            $id = $this->positiveId($campaignId);
            $payload = $this->jsonPayload();
            $this->exactKeys($payload, ['sceneId', 'tokenId', 'revision', 'x', 'y', 'waypoints']);
            return $this->respond($this->tokens->update(
                $id,
                $this->positiveId($payload['sceneId'] ?? null),
                $this->positiveId($payload['tokenId'] ?? null),
                $this->principals->resolve(
                    $this->request->getHeaderLine('Authorization'),
                    $this->request->getHeaderLine('X-Realtime-Client-Instance'),
                    $id
                ),
                [
                    'revision' => $payload['revision'] ?? null,
                    'x' => $payload['x'] ?? null,
                    'y' => $payload['y'] ?? null,
                ],
                $payload['waypoints'] ?? []
            ));
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

    private function positiveId($value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new CampaignException('not_found', 'Resource was not found.', 404);
        return (int) $id;
    }

    private function exactKeys(array $payload, array $allowed): void
    {
        $unexpected = array_diff(array_keys($payload), $allowed);
        if ($unexpected) {
            throw new CampaignException('validation_failed', 'Token move is invalid.', 422,
                array_fill_keys($unexpected, 'This field is not accepted.'));
        }
    }

    private function failure($exception)
    {
        $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
        if ($exception->details()) $payload['errors'] = $exception->details();
        return $this->response->setStatusCode($exception->status())->setJSON($payload);
    }
}
