<?php

namespace App\Services\Auth;

use Config\Services;

final class OAuthProviderClient
{
    private $providers;
    private $request;

    public function __construct(?OAuthProviderRegistry $providers = null, ?callable $request = null)
    {
        $this->providers = $providers ?: new OAuthProviderRegistry();
        $this->request = $request ?: static function (string $method, string $url, array $options): array {
            try {
                $response = Services::curlrequest()->request($method, $url, $options + [
                    'connect_timeout' => 4,
                    'timeout' => 10,
                    'http_errors' => false,
                ]);
                $payload = json_decode((string) $response->getBody(), true);
                return [
                    'status' => (int) $response->getStatusCode(),
                    'body' => is_array($payload) ? $payload : [],
                ];
            } catch (\Throwable $exception) {
                throw new AuthException(
                    'oauth_provider_unavailable',
                    'OAuth provider could not be reached.',
                    502
                );
            }
        };
    }

    public function authorizationUrl(string $provider, string $state): string
    {
        $config = $this->providers->configuration($provider);
        $query = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => $config['scope'],
            'state' => $state,
        ];
        if ($provider === 'google') {
            $query['prompt'] = 'select_account';
            $query['include_granted_scopes'] = 'true';
        }
        return $config['authorization_url'] . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public function identity(string $provider, string $code): array
    {
        if ($code === '' || strlen($code) > 4096) {
            throw new AuthException('oauth_code_invalid', 'OAuth authorization code is invalid.', 400);
        }
        $config = $this->providers->configuration($provider);
        $token = $this->requestJson('POST', $config['token_url'], [
            'form_params' => [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $config['redirect_uri'],
            ],
            'headers' => ['Accept' => 'application/json'],
        ]);
        $accessToken = is_string($token['access_token'] ?? null)
            ? trim($token['access_token']) : '';
        if ($accessToken === '') {
            throw new AuthException('oauth_token_invalid', 'OAuth provider returned an invalid token.', 502);
        }

        $options = [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
                'User-Agent' => 'BlatyRPG OAuth',
            ],
        ];
        if ($provider === 'facebook') {
            $options['query'] = ['fields' => 'id,name,email,picture.type(large)'];
        }
        $profile = $this->requestJson('GET', $config['profile_url'], $options);
        return $this->normalizeIdentity($provider, $profile);
    }

    public function normalizeIdentity(string $provider, array $profile): array
    {
        $subject = trim((string) ($profile['sub'] ?? $profile['id'] ?? ''));
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        $name = trim((string) ($profile['name'] ?? $profile['global_name']
            ?? $profile['username'] ?? ''));
        $avatar = null;
        $verified = false;

        if ($provider === 'google') {
            $verified = ($profile['email_verified'] ?? false) === true;
            $avatar = $profile['picture'] ?? null;
        } elseif ($provider === 'facebook') {
            // Facebook exposes only an app-scoped email granted by the account owner.
            $verified = $email !== '';
            $avatar = $profile['picture']['data']['url'] ?? null;
        } elseif ($provider === 'discord') {
            $verified = ($profile['verified'] ?? false) === true;
            $avatarHash = trim((string) ($profile['avatar'] ?? ''));
            if ($avatarHash !== '' && $subject !== '') {
                $avatar = 'https://cdn.discordapp.com/avatars/' . rawurlencode($subject)
                    . '/' . rawurlencode($avatarHash) . '.png?size=256';
            }
        } else {
            throw new AuthException('oauth_provider_unknown', 'OAuth provider is not supported.', 404);
        }

        if ($subject === '' || strlen($subject) > 191) {
            throw new AuthException('oauth_profile_invalid', 'OAuth provider returned an invalid profile.', 502);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
            $verified = false;
        }
        if (!is_string($avatar) || strpos($avatar, 'https://') !== 0 || strlen($avatar) > 255) {
            $avatar = null;
        }
        return [
            'provider' => $provider,
            'subject' => $subject,
            'email' => $email,
            'email_verified' => $verified,
            'name' => mb_substr($name, 0, 100),
            'avatar_url' => $avatar,
        ];
    }

    private function requestJson(string $method, string $url, array $options): array
    {
        $response = ($this->request)($method, $url, $options);
        $status = (int) ($response['status'] ?? 0);
        $body = $response['body'] ?? null;
        if ($status < 200 || $status >= 300 || !is_array($body)) {
            throw new AuthException(
                'oauth_provider_rejected',
                'OAuth provider rejected the request.',
                502
            );
        }
        return $body;
    }
}
