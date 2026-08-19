<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Auth\AuthContextService;
use App\Services\Chat\CampaignChatException;
use App\Services\Chat\CampaignChatService;
use CodeIgniter\API\ResponseTrait;

class CampaignChatController extends BaseController
{
    use ResponseTrait;

    private $authContext;
    private $chat;

    public function __construct()
    {
        $this->authContext = new AuthContextService();
        $this->chat = new CampaignChatService();
    }

    public function index($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->chat->list(
                (int) $campaignId,
                $this->authContext->resolveFromRequest($this->request),
                $this->request->getGet()
            );
        });
    }

    public function create($campaignId = null)
    {
        try {
            $result = $this->chat->send(
                (int) $campaignId,
                $this->authContext->resolveFromRequest($this->request),
                $this->jsonPayload()
            );
            return $this->respond($result, !empty($result['duplicate']) ? 200 : 201);
        } catch (CampaignChatException $exception) {
            return $this->errorResponse($exception);
        }
    }

    private function jsonPayload(): array
    {
        try {
            $payload = $this->request->getJSON(true);
        } catch (\Throwable $exception) {
            throw new CampaignChatException('invalid_json', 'Request body must contain valid JSON.', 400);
        }
        if (!is_array($payload)) {
            throw new CampaignChatException('invalid_json', 'Request body must be a JSON object.', 400);
        }
        return $payload;
    }

    private function execute(callable $operation)
    {
        try {
            return $this->respond($operation());
        } catch (CampaignChatException $exception) {
            return $this->errorResponse($exception);
        }
    }

    private function errorResponse(CampaignChatException $exception)
    {
        $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
        if ($exception->details()) {
            $payload['errors'] = $exception->details();
        }
        if ($exception->status() === 429 && isset($exception->details()['retryAfter'])) {
            $this->response->setHeader('Retry-After', (string) $exception->details()['retryAfter']);
        }
        return $this->response->setStatusCode($exception->status())->setJSON($payload);
    }
}
