<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Chat\CampaignChatException;
use App\Services\Chat\CampaignChatService;
use App\Services\Realtime\RealtimePrincipalService;
use CodeIgniter\API\ResponseTrait;

/** Docker-network-only adapter; domain authorization remains in CampaignChatService. */
class InternalRealtimeChatController extends BaseController
{
    use ResponseTrait;

    private $principals;
    private $chat;

    public function __construct(
        ?RealtimePrincipalService $principals = null,
        ?CampaignChatService $chat = null
    ) {
        $this->principals = $principals ?: new RealtimePrincipalService();
        $this->chat = $chat ?: new CampaignChatService();
    }

    public function sync($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            $id = (int) $campaignId;
            $payload = $this->jsonPayload();
            $this->exactKeys($payload, ['afterRevision', 'beforeRevision', 'limit']);
            $query = [
                'afterId' => $payload['afterRevision'] ?? null,
                'beforeId' => $payload['beforeRevision'] ?? null,
                'limit' => $payload['limit'] ?? null,
            ];
            return $this->chat->list($id, $this->principal($id), array_filter(
                $query,
                static function ($value): bool {
                    return $value !== null;
                }
            ));
        });
    }

    public function send($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            $id = (int) $campaignId;
            $result = $this->chat->send($id, $this->principal($id), $this->jsonPayload());
            $result['_httpStatus'] = !empty($result['duplicate']) ? 200 : 201;
            return $result;
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
            throw new CampaignChatException('invalid_json', 'Valid JSON is required.', 400);
        }
        if (!is_array($payload)) {
            throw new CampaignChatException('invalid_json', 'A JSON object is required.', 400);
        }
        return $payload;
    }

    private function exactKeys(array $payload, array $allowed): void
    {
        $unexpected = array_diff(array_keys($payload), $allowed);
        if ($unexpected) {
            throw new CampaignChatException(
                'validation_failed',
                'Realtime chat payload is invalid.',
                422,
                array_fill_keys($unexpected, 'This field is not accepted.')
            );
        }
    }

    private function execute(callable $operation)
    {
        try {
            $result = $operation();
            $status = (int) ($result['_httpStatus'] ?? 200);
            unset($result['_httpStatus']);
            return $this->respond($result, $status);
        } catch (CampaignChatException $exception) {
            $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
            if ($exception->details()) {
                $payload['errors'] = $exception->details();
            }
            if ($exception->status() === 429 && isset($exception->details()['retryAfter'])) {
                $this->response->setHeader(
                    'Retry-After',
                    (string) $exception->details()['retryAfter']
                );
            }
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }
}
