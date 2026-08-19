<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\Auth\AuthAccountService;
use App\Services\Auth\AuthContextService;
use App\Services\Auth\AuthException;
use App\Services\Auth\AuthPayloadValidator;
use App\Services\Auth\AuthSessionService;
use CodeIgniter\API\ResponseTrait;

class AuthSessionController extends BaseController
{
    use ResponseTrait;

    private $authContext;
    private $sessions;
    private $accounts;
    private $payloadValidator;

    public function __construct()
    {
        $db = \Config\Database::connect();
        $this->sessions = new AuthSessionService(null, new UserModel($db));
        $this->authContext = new AuthContextService($this->sessions);
        $this->accounts = new AuthAccountService($db, new UserModel($db), $this->sessions);
        $this->payloadValidator = new AuthPayloadValidator();
    }

    public function me()
    {
        return $this->execute(function (): array {
            $auth = $this->auth();
            $user = $this->accounts->activeUser((int) $auth['user_id']);
            return ['user' => $this->accounts->present($user)];
        });
    }

    public function logout()
    {
        return $this->execute(function (): array {
            $auth = $this->auth();
            $this->sessions->revoke((int) $auth['session_id'], (int) $auth['user_id']);
            return ['message' => 'Signed out successfully.'];
        });
    }

    public function updateProfile()
    {
        return $this->execute(function (): array {
            $auth = $this->auth();
            $validated = $this->payloadValidator->profile($this->payload());
            $this->assertValid($validated, 'profile_invalid');
            $user = $this->accounts->updateProfile((int) $auth['user_id'], $validated['data']);
            return ['user' => $this->accounts->present($user)];
        });
    }

    public function changePassword()
    {
        return $this->execute(function (): array {
            $auth = $this->auth();
            $validated = $this->payloadValidator->changePassword($this->payload());
            $this->assertValid($validated, 'password_invalid');
            $result = $this->accounts->changePassword(
                (int) $auth['user_id'],
                $validated['data']['current_password'],
                $validated['data']['new_password'],
                (string) $this->request->getIPAddress(),
                substr($this->request->getHeaderLine('User-Agent'), 0, 512)
            );
            return [
                'status' => 'success',
                'access_token' => $result['session']['access_token'],
                'token_type' => $result['session']['token_type'],
                'expires_in' => $result['session']['expires_in'],
                'user' => $this->accounts->present($result['user']),
            ];
        });
    }

    private function auth(): array
    {
        $auth = $this->authContext->resolveFromRequest($this->request);
        if (($auth['authentication_error'] ?? null) === 'configuration_error') {
            throw new AuthException('auth_unavailable', 'Authentication is temporarily unavailable.', 503);
        }
        if (empty($auth['authenticated']) || !empty($auth['anonymous'])) {
            throw new AuthException('unauthorized', 'Authentication is required.', 401);
        }
        return $auth;
    }

    private function payload(): array
    {
        $contentType = strtolower($this->request->getHeaderLine('Content-Type'));
        try {
            $payload = strpos($contentType, 'application/json') !== false
                ? $this->request->getJSON(true) : $this->request->getVar();
        } catch (\Throwable $exception) {
            throw new AuthException('invalid_json', 'Request body must contain valid JSON.', 400);
        }
        if (!is_array($payload)) {
            throw new AuthException('invalid_payload', 'Request body must be an object.', 400);
        }
        return $payload;
    }

    private function assertValid(array $validated, string $code): void
    {
        if (!$validated['valid']) {
            throw new AuthException($code, 'Request payload is invalid.', 422, $validated['errors']);
        }
    }

    private function execute(callable $operation)
    {
        try {
            return $this->respond($operation());
        } catch (AuthException $exception) {
            $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
            if ($exception->details()) {
                $payload['errors'] = $exception->details();
            }
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }
}
