<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Services\Auth\AuthAccountService;
use App\Services\Auth\AuthException;
use App\Services\Auth\AuthPayloadValidator;
use App\Services\Auth\AuthRateLimiter;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\PasswordResetService;
use CodeIgniter\RESTful\ResourceController;

class AuthController extends ResourceController
{
    private const RESET_RESPONSE = 'If the account exists, password reset instructions have been sent.';

    private $payloadValidator;
    private $accounts;
    private $sessions;
    private $resets;
    private $limiter;

    public function __construct()
    {
        $db = \Config\Database::connect();
        $users = new UserModel($db);
        $this->sessions = new AuthSessionService(null, new UserModel($db));
        $this->accounts = new AuthAccountService($db, $users, $this->sessions);
        $this->payloadValidator = new AuthPayloadValidator();
        $this->resets = new PasswordResetService($db, null, new UserModel($db), $this->accounts);
        $this->limiter = new AuthRateLimiter();
    }

    public function register()
    {
        return $this->execute(function (): array {
            $payload = $this->payload();
            $validated = $this->payloadValidator->register($payload);
            $this->throttle('register', $validated['data']['email'] ?? '');
            $this->assertValid($validated, 'registration_invalid');
            $user = $this->accounts->register($validated['data']);
            return [
                'message' => 'Registration completed. You can now sign in.',
                'user' => $this->accounts->present($user),
            ];
        }, 201);
    }

    public function login()
    {
        return $this->execute(function (): array {
            $payload = $this->payload();
            $validated = $this->payloadValidator->login($payload);
            $this->throttle('login', $validated['data']['identifier'] ?? '');
            if (!$validated['valid']) {
                throw new AuthException('invalid_credentials', 'Invalid login or password.', 401);
            }
            $user = $this->accounts->authenticate(
                $validated['data']['identifier'],
                $validated['data']['password']
            );
            $session = $this->sessions->issue($user, $this->ip(), $this->userAgent());
            return $this->sessionPayload($user, $session);
        });
    }

    public function requestPasswordReset()
    {
        return $this->execute(function (): array {
            $payload = $this->payload();
            $validated = $this->payloadValidator->resetRequest($payload);
            $this->throttle('reset_request', $validated['data']['email'] ?? '');
            if ($validated['valid']) {
                $this->resets->request($validated['data']['email'], $this->ip());
            }
            return ['message' => self::RESET_RESPONSE];
        });
    }

    public function resetPassword()
    {
        return $this->execute(function (): array {
            $payload = $this->payload();
            $validated = $this->payloadValidator->resetConfirm($payload);
            $this->throttle('reset_confirm', $validated['data']['token'] ?? '');
            $this->assertValid($validated, 'reset_invalid');
            $result = $this->resets->confirm(
                $validated['data']['token'],
                $validated['data']['password'],
                $this->ip(),
                $this->userAgent()
            );
            return $this->sessionPayload($result['user'], $result['session']);
        });
    }

    private function sessionPayload(array $user, array $session): array
    {
        return [
            'status' => 'success',
            'access_token' => $session['access_token'],
            'token_type' => $session['token_type'],
            'expires_in' => $session['expires_in'],
            'user' => $this->accounts->present($user),
        ];
    }

    private function payload(): array
    {
        $contentType = strtolower($this->request->getHeaderLine('Content-Type'));
        if (strpos($contentType, 'application/json') !== false) {
            try {
                $payload = $this->request->getJSON(true);
            } catch (\Throwable $exception) {
                throw new AuthException('invalid_json', 'Request body must contain valid JSON.', 400);
            }
        } else {
            $payload = $this->request->getVar();
        }
        if (!is_array($payload)) {
            throw new AuthException('invalid_payload', 'Request body must be an object.', 400);
        }
        return $payload;
    }

    private function throttle(string $action, string $identity): void
    {
        $result = $this->limiter->consume($action, $this->ip(), substr($identity, 0, 255));
        if (!$result['allowed']) {
            throw new AuthException(
                'rate_limited',
                'Too many authentication attempts. Try again later.',
                429,
                ['retryAfter' => (int) $result['retry_after']]
            );
        }
    }

    private function assertValid(array $validated, string $code): void
    {
        if (!$validated['valid']) {
            throw new AuthException($code, 'Request payload is invalid.', 422, $validated['errors']);
        }
    }

    private function execute(callable $operation, int $status = 200)
    {
        try {
            return $this->respond($operation(), $status);
        } catch (AuthException $exception) {
            $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
            if ($exception->details()) {
                $payload['errors'] = $exception->details();
            }
            $response = $this->response->setStatusCode($exception->status())->setJSON($payload);
            if ($exception->status() === 429) {
                $response->setHeader('Retry-After', (string) ($exception->details()['retryAfter'] ?? 1));
            }
            return $response;
        }
    }

    private function ip(): string
    {
        return (string) $this->request->getIPAddress();
    }

    private function userAgent(): string
    {
        return substr($this->request->getHeaderLine('User-Agent'), 0, 512);
    }
}
