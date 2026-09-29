<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\Auth\AuthAccountService;
use App\Services\Auth\AuthContextService;
use App\Services\Auth\AuthException;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\AuthRateLimiter;
use App\Services\Auth\OAuthFlowService;
use App\Services\Auth\OAuthProviderClient;
use App\Services\Auth\OAuthProviderRegistry;
use CodeIgniter\API\ResponseTrait;

class OAuthController extends BaseController
{
    use ResponseTrait;

    private const STATE_COOKIE_PREFIX = 'blatyrpg_oauth_';
    private const STATE_COOKIE_PATH = '/api/auth/oauth';
    private const STATE_COOKIE_TTL = 600;

    private $providers;
    private $client;
    private $flows;
    private $sessions;
    private $accounts;
    private $authContext;
    private $limiter;

    public function __construct()
    {
        $db = \Config\Database::connect();
        $users = new UserModel($db);
        $this->providers = new OAuthProviderRegistry();
        $this->client = new OAuthProviderClient($this->providers);
        $this->sessions = new AuthSessionService(null, new UserModel($db));
        $this->accounts = new AuthAccountService($db, $users, $this->sessions);
        $this->flows = new OAuthFlowService($db, $users, $this->accounts);
        $this->authContext = new AuthContextService($this->sessions);
        $this->limiter = new AuthRateLimiter();
    }

    public function providers()
    {
        return $this->execute(fn (): array => ['providers' => $this->providers->summaries()]);
    }

    public function start($provider)
    {
        return $this->execute(function () use ($provider): array {
            $this->throttle('oauth_start');
            $config = $this->providers->configuration((string) $provider);
            $flow = $this->flows->createState($config['provider'], 'login', null);
            $this->setStateCookie($config, $flow['browserToken']);
            return [
                'authorizationUrl' => $this->client->authorizationUrl(
                    $config['provider'],
                    $flow['state']
                ),
            ];
        });
    }

    public function link($provider)
    {
        return $this->execute(function () use ($provider): array {
            $this->throttle('oauth_start');
            $auth = $this->auth();
            $config = $this->providers->configuration((string) $provider);
            $flow = $this->flows->createState(
                $config['provider'],
                'link',
                (int) $auth['user_id']
            );
            $this->setStateCookie($config, $flow['browserToken']);
            return [
                'authorizationUrl' => $this->client->authorizationUrl(
                    $config['provider'],
                    $flow['state']
                ),
            ];
        });
    }

    public function callback($provider)
    {
        $provider = strtolower(trim((string) $provider));
        try {
            $this->providers->configuration($provider);
            $state = $this->flows->consumeState(
                $provider,
                trim((string) $this->request->getGet('state')),
                trim((string) $this->request->getCookie($this->stateCookieName($provider)))
            );
            $providerError = trim((string) $this->request->getGet('error'));
            if ($providerError !== '') {
                throw new AuthException('oauth_cancelled', 'OAuth authorization was cancelled.', 400);
            }
            $identity = $this->client->identity(
                $provider,
                trim((string) $this->request->getGet('code'))
            );
            if (($state['intent'] ?? '') === 'link') {
                $this->flows->link((int) $state['user_id'], $identity);
                return $this->callbackRedirect($provider, [
                    'oauthStatus' => 'linked',
                    'provider' => $provider,
                ]);
            }
            $user = $this->flows->loginOrCreate($identity);
            return $this->callbackRedirect($provider, [
                'code' => $this->flows->issueLoginCode((int) $user['id']),
                'provider' => $provider,
            ]);
        } catch (AuthException $exception) {
            return $this->callbackRedirect($provider, [
                'oauthError' => $exception->errorCode(),
                'provider' => $provider,
            ]);
        } catch (\Throwable $exception) {
            log_message('error', 'OAuth callback failed: {message}', ['message' => $exception->getMessage()]);
            return $this->callbackRedirect($provider, [
                'oauthError' => 'oauth_callback_failed',
                'provider' => $provider,
            ]);
        }
    }

    public function exchange()
    {
        return $this->execute(function (): array {
            $this->throttle('oauth_exchange');
            $payload = $this->payload();
            $code = is_string($payload['code'] ?? null) ? trim($payload['code']) : '';
            $user = $this->flows->consumeLoginCode($code);
            $session = $this->sessions->issue($user, $this->ip(), $this->userAgent());
            return $this->sessionPayload($user, $session);
        });
    }

    public function identities()
    {
        return $this->execute(function (): array {
            $auth = $this->auth();
            return ['identities' => $this->flows->identities((int) $auth['user_id'])];
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

    private function callbackUrl(array $query): string
    {
        $url = $this->providers->frontendCallbackUrl();
        return $url . (strpos($url, '?') === false ? '?' : '&')
            . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function callbackRedirect(string $provider, array $query)
    {
        $response = redirect()->to($this->callbackUrl($query));
        $response->deleteCookie(
            $this->stateCookieName($provider),
            '',
            self::STATE_COOKIE_PATH
        );
        return $response;
    }

    private function setStateCookie(array $config, string $browserToken): void
    {
        $this->response->setCookie(
            $this->stateCookieName($config['provider']),
            $browserToken,
            self::STATE_COOKIE_TTL,
            '',
            self::STATE_COOKIE_PATH,
            '',
            strpos($config['redirect_uri'], 'https://') === 0,
            true,
            'Lax'
        );
    }

    private function stateCookieName(string $provider): string
    {
        $safeProvider = preg_replace('/[^a-z0-9_-]/', '', strtolower($provider));
        return self::STATE_COOKIE_PREFIX . $safeProvider;
    }

    private function payload(): array
    {
        try {
            $payload = $this->request->getJSON(true);
        } catch (\Throwable $exception) {
            throw new AuthException('invalid_json', 'Request body must contain valid JSON.', 400);
        }
        if (!is_array($payload)) {
            throw new AuthException('invalid_payload', 'Request body must be an object.', 400);
        }
        return $payload;
    }

    private function execute(callable $operation)
    {
        try {
            return $this->respond($operation());
        } catch (AuthException $exception) {
            $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
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

    private function throttle(string $action): void
    {
        $result = $this->limiter->consume($action, $this->ip());
        if (!$result['allowed']) {
            throw new AuthException(
                'rate_limited',
                'Too many authentication attempts. Try again later.',
                429,
                ['retryAfter' => (int) $result['retry_after']]
            );
        }
    }
}
