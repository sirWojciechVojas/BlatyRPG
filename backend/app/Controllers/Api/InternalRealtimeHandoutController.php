<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Campaign\CampaignException;
use App\Services\Chat\CampaignChatException;
use App\Services\Handout\HandoutService;
use App\Services\Realtime\RealtimePrincipalService;
use CodeIgniter\API\ResponseTrait;

/** Docker-network-only delivery lookup for targeted handout WebSocket events. */
class InternalRealtimeHandoutController extends BaseController
{
    use ResponseTrait;

    private $principals;
    private $handouts;

    public function __construct(?RealtimePrincipalService $principals = null, ?HandoutService $handouts = null)
    {
        $this->principals = $principals ?: new RealtimePrincipalService();
        $this->handouts = $handouts ?: new HandoutService();
    }

    public function delivery($campaignId = null)
    {
        try {
            $id = $this->positiveId($campaignId);
            $payload = $this->jsonPayload();
            if (array_diff(array_keys($payload), ['batchId'])) {
                throw new CampaignException('validation_failed', 'Delivery request is invalid.', 422);
            }
            $batchId = strtolower(trim((string) ($payload['batchId'] ?? '')));
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $batchId)) {
                throw new CampaignException('validation_failed', 'Delivery batch is invalid.', 422);
            }
            return $this->respond($this->handouts->deliveryTargets($id, $batchId, $this->principal($id)));
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
        if (!is_array($payload)) throw new CampaignException('invalid_json', 'A JSON object is required.', 400);
        return $payload;
    }

    private function principal(int $campaignId): array
    {
        return $this->principals->resolve(
            $this->request->getHeaderLine('Authorization'),
            $this->request->getHeaderLine('X-Realtime-Client-Instance'),
            $campaignId
        );
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
