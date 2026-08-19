<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Admin\AdminException;
use App\Services\Admin\AdminService;
use App\Services\Auth\AuthContextService;
use CodeIgniter\API\ResponseTrait;

class AdminController extends BaseController
{
    use ResponseTrait;

    private $authContext;
    private $admin;

    public function __construct()
    {
        $this->authContext = new AuthContextService();
        $this->admin = new AdminService();
    }

    public function overview()
    {
        return $this->execute(function (): array {
            return $this->admin->overview($this->auth());
        });
    }

    public function createUser()
    {
        return $this->execute(function (): array {
            return $this->admin->createUser($this->auth(), $this->jsonPayload());
        }, 201);
    }

    public function changeUserRole($userId = null)
    {
        return $this->execute(function () use ($userId): array {
            return $this->admin->changeUserRole(
                $this->auth(),
                (int) $userId,
                $this->jsonPayload()
            );
        });
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
            throw new AdminException('invalid_json', 'Request body must contain valid JSON.', 400);
        }
        if (!is_array($payload)) {
            throw new AdminException('invalid_json', 'Request body must be a JSON object.', 400);
        }
        return $payload;
    }

    private function execute(callable $operation, int $successStatus = 200)
    {
        try {
            return $this->respond($operation(), $successStatus);
        } catch (AdminException $exception) {
            $payload = [
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ];
            if ($exception->details()) {
                $payload['errors'] = $exception->details();
            }
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }
}
