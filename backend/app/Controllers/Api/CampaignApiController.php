<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Auth\AuthContextService;
use App\Services\Campaign\CampaignException;
use CodeIgniter\API\ResponseTrait;

abstract class CampaignApiController extends BaseController
{
    use ResponseTrait;

    private $campaignAuth;

    public function __construct()
    {
        $this->campaignAuth = new AuthContextService();
    }

    protected function auth(): array
    {
        return $this->campaignAuth->resolveFromRequest($this->request);
    }

    protected function jsonPayload(): array
    {
        try {
            $payload = $this->request->getJSON(true);
        } catch (\Throwable $exception) {
            throw new CampaignException('invalid_json', 'Request body must contain valid JSON.', 400);
        }
        if (!is_array($payload)) {
            throw new CampaignException('invalid_json', 'Request body must be a JSON object.', 400);
        }
        return $payload;
    }

    protected function positiveId($value, string $code = 'not_found'): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new CampaignException($code, 'Resource was not found.', 404);
        }
        return (int) $id;
    }

    protected function execute(callable $operation, int $successStatus = 200)
    {
        try {
            return $this->respond($operation(), $successStatus);
        } catch (CampaignException $exception) {
            $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
            if ($exception->details()) {
                $payload['errors'] = $exception->details();
            }
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }
}
