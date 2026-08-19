<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Auth\AuthContextService;
use App\Services\Campaign\CampaignDirectoryService;
use App\Services\Campaign\CampaignException;
use CodeIgniter\API\ResponseTrait;

class CampaignController extends BaseController
{
    use ResponseTrait;

    private $authContext;
    private $campaigns;

    public function __construct()
    {
        $this->authContext = new AuthContextService();
        $this->campaigns = new CampaignDirectoryService();
    }

    public function index()
    {
        return $this->execute(function (): array {
            return $this->campaigns->listForUser($this->auth());
        });
    }

    public function create()
    {
        return $this->execute(function (): array {
            return $this->campaigns->create($this->auth(), $this->jsonPayload());
        }, 201);
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
            throw new CampaignException('invalid_json', 'Request body must contain valid JSON.', 400);
        }
        if (!is_array($payload)) {
            throw new CampaignException('invalid_json', 'Request body must be a JSON object.', 400);
        }
        return $payload;
    }

    private function execute(callable $operation, int $successStatus = 200)
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
