<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Auth\AuthContextService;
use App\Services\Fog\FogException;
use App\Services\Fog\SceneFogService;
use CodeIgniter\API\ResponseTrait;

class SceneFogController extends BaseController
{
    use ResponseTrait;
    private $authContext;
    private $fog;

    public function __construct()
    {
        $this->authContext = new AuthContextService();
        $this->fog = new SceneFogService();
    }

    public function show($campaignId = null, $sceneId = null)
    {
        return $this->execute(fn (): array => $this->fog->get(
            (int) $campaignId, (int) $sceneId,
            $this->authContext->resolveFromRequest($this->request),
            $this->request->getGet('userId') ? (int) $this->request->getGet('userId') : null
        ));
    }

    public function patch($campaignId = null, $sceneId = null)
    {
        return $this->execute(fn (): array => $this->fog->patch(
            (int) $campaignId, (int) $sceneId,
            $this->authContext->resolveFromRequest($this->request), $this->payload()
        ));
    }

    private function payload(): array
    {
        try { $payload = $this->request->getJSON(true); }
        catch (\Throwable $exception) { $payload = null; }
        if (!is_array($payload)) throw new FogException('invalid_json', 'A JSON object is required.', 400);
        return $payload;
    }

    private function execute(callable $operation)
    {
        try { return $this->respond($operation()); }
        catch (FogException $exception) {
            $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
            if ($exception->details()) $payload['errors'] = $exception->details();
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }
}
