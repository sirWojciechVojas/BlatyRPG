<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Campaign\CampaignException;
use App\Services\Chat\CampaignChatException;
use App\Services\Realtime\RealtimePrincipalService;
use App\Services\Token\TokenSyncService;
use CodeIgniter\API\ResponseTrait;

class InternalRealtimeTokenSyncController extends BaseController
{
    use ResponseTrait;

    private $principals;
    private $sync;

    public function __construct(
        ?RealtimePrincipalService $principals = null,
        ?TokenSyncService $sync = null
    ) {
        $this->principals = $principals ?: new RealtimePrincipalService();
        $this->sync = $sync ?: new TokenSyncService();
    }

    public function command($campaignId = null)
    {
        try {
            $id = $this->positiveId($campaignId);
            $payload = $this->jsonPayload();
            $unexpected = array_diff(array_keys($payload), ['action', 'data']);
            if ($unexpected || !is_array($payload['data'] ?? null)) {
                throw new CampaignException('validation_failed', 'Token sync command is invalid.', 422);
            }
            $auth = $this->principals->resolve(
                $this->request->getHeaderLine('Authorization'),
                $this->request->getHeaderLine('X-Realtime-Client-Instance'),
                $id
            );
            $data = $payload['data'];
            switch ((string) ($payload['action'] ?? '')) {
                case 'transfer':
                    $result = $this->sync->transfer($id, $auth, $data);
                    break;
                case 'createLinks':
                    $result = $this->sync->createLinks($id, $auth, $data);
                    break;
                case 'updateLink':
                    $result = $this->sync->updateLink(
                        $id, $this->positiveId($data['linkId'] ?? null), $auth,
                        ['enabled' => $data['enabled'] ?? null]
                    );
                    break;
                case 'applyLink':
                    $result = $this->sync->applyLink(
                        $id, $this->positiveId($data['linkId'] ?? null), $auth
                    );
                    break;
                case 'deleteLink':
                    $result = $this->sync->deleteLink(
                        $id, $this->positiveId($data['linkId'] ?? null), $auth
                    );
                    break;
                default:
                    throw new CampaignException('validation_failed', 'Token sync action is invalid.', 422);
            }
            return $this->respond($result);
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
