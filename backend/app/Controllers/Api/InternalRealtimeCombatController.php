<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Campaign\CampaignException;
use App\Services\Chat\CampaignChatException;
use App\Services\Combat\SceneCombatService;
use App\Services\Realtime\RealtimePrincipalService;
use CodeIgniter\API\ResponseTrait;

class InternalRealtimeCombatController extends BaseController
{
    use ResponseTrait;

    private $principals;
    private $combats;

    public function __construct(
        ?RealtimePrincipalService $principals = null,
        ?SceneCombatService $combats = null
    ) {
        $this->principals = $principals ?: new RealtimePrincipalService();
        $this->combats = $combats ?: new SceneCombatService();
    }

    public function command($campaignId = null)
    {
        try {
            $id = $this->positiveId($campaignId);
            $payload = $this->jsonPayload();
            $sceneId = $this->positiveId($payload['sceneId'] ?? null);
            unset($payload['sceneId']);
            return $this->respond($this->combats->command(
                $id,
                $sceneId,
                $this->principals->resolve(
                    $this->request->getHeaderLine('Authorization'),
                    $this->request->getHeaderLine('X-Realtime-Client-Instance'),
                    $id
                ),
                $payload
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
        if (!is_array($payload)) throw new CampaignException('invalid_json', 'A JSON object is required.', 400);
        return $payload;
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
