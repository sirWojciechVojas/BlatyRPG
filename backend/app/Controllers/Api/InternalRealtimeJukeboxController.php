<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Audio\JukeboxStateService;
use App\Services\Campaign\CampaignException;
use App\Services\Chat\CampaignChatException;
use App\Services\Realtime\RealtimePrincipalService;
use CodeIgniter\API\ResponseTrait;

/** Docker-network-only persistence adapter for the authoritative WebSocket process. */
class InternalRealtimeJukeboxController extends BaseController
{
    use ResponseTrait;

    private $principals;
    private $jukebox;

    public function __construct(
        ?RealtimePrincipalService $principals = null,
        ?JukeboxStateService $jukebox = null
    ) {
        $this->principals = $principals ?: new RealtimePrincipalService();
        $this->jukebox = $jukebox ?: new JukeboxStateService();
    }

    public function state($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            $id = $this->positiveId($campaignId);
            return $this->jukebox->state($this->principal($id), $id);
        });
    }

    public function save($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            $id = $this->positiveId($campaignId);
            return $this->jukebox->save($this->principal($id), $id, $this->jsonPayload());
        });
    }

    private function principal(int $campaignId): array
    {
        return $this->principals->resolve(
            $this->request->getHeaderLine('Authorization'),
            $this->request->getHeaderLine('X-Realtime-Client-Instance'),
            $campaignId
        );
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
        if ($id === false) {
            throw new CampaignException('not_found', 'Resource was not found.', 404);
        }
        return (int) $id;
    }

    private function execute(callable $operation)
    {
        try {
            return $this->respond($operation());
        } catch (CampaignException $exception) {
            return $this->failure($exception);
        } catch (CampaignChatException $exception) {
            return $this->failure($exception);
        }
    }

    private function failure($exception)
    {
        $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
        if ($exception->details()) {
            $payload['errors'] = $exception->details();
        }
        return $this->response->setStatusCode($exception->status())->setJSON($payload);
    }
}
