<?php

namespace App\Services\Auth;

final class OAuthProviderRegistry
{
    private const DEFINITIONS = [
        'google' => [
            'label' => 'Google',
            'authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'profile_url' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scope' => 'openid email profile',
        ],
        'facebook' => [
            'label' => 'Facebook',
            'authorization_url' => 'https://www.facebook.com/v23.0/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/v23.0/oauth/access_token',
            'profile_url' => 'https://graph.facebook.com/v23.0/me',
            'scope' => 'email public_profile',
        ],
        'discord' => [
            'label' => 'Discord',
            'authorization_url' => 'https://discord.com/oauth2/authorize',
            'token_url' => 'https://discord.com/api/oauth2/token',
            'profile_url' => 'https://discord.com/api/v10/users/@me',
            'scope' => 'identify email',
        ],
    ];

    private $values;

    public function __construct(?array $values = null)
    {
        $read = static function (string $key) use ($values): string {
            if (is_array($values) && array_key_exists($key, $values)) {
                return trim((string) $values[$key]);
            }
            $value = getenv($key);
            return trim(is_string($value) ? $value : '');
        };
        $this->values = [
            'callback_base_url' => rtrim($read('OAUTH_CALLBACK_BASE_URL'), '/'),
            'frontend_callback_url' => $read('OAUTH_FRONTEND_CALLBACK_URL'),
            'credentials' => [],
        ];
        foreach (array_keys(self::DEFINITIONS) as $provider) {
            $prefix = strtoupper($provider) . '_OAUTH_';
            $this->values['credentials'][$provider] = [
                'client_id' => $read($prefix . 'CLIENT_ID'),
                'client_secret' => $read($prefix . 'CLIENT_SECRET'),
            ];
        }
    }

    public function summaries(): array
    {
        $summaries = [];
        foreach (self::DEFINITIONS as $provider => $definition) {
            $summaries[] = [
                'provider' => $provider,
                'label' => $definition['label'],
                'enabled' => $this->enabled($provider),
            ];
        }
        return $summaries;
    }

    public function configuration(string $provider): array
    {
        $provider = strtolower(trim($provider));
        if (!isset(self::DEFINITIONS[$provider])) {
            throw new AuthException('oauth_provider_unknown', 'OAuth provider is not supported.', 404);
        }
        if (!$this->enabled($provider)) {
            throw new AuthException('oauth_provider_unavailable', 'OAuth provider is not configured.', 503);
        }
        return self::DEFINITIONS[$provider] + $this->values['credentials'][$provider] + [
            'provider' => $provider,
            'redirect_uri' => $this->values['callback_base_url'] . '/' . $provider . '/callback',
        ];
    }

    public function frontendCallbackUrl(): string
    {
        if (!$this->validHttpsOrLocalUrl($this->values['frontend_callback_url'])) {
            throw new AuthException('oauth_unavailable', 'OAuth callback is not configured.', 503);
        }
        return $this->values['frontend_callback_url'];
    }

    private function enabled(string $provider): bool
    {
        $credentials = $this->values['credentials'][$provider] ?? [];
        return ($credentials['client_id'] ?? '') !== ''
            && ($credentials['client_secret'] ?? '') !== ''
            && $this->validHttpsOrLocalUrl($this->values['callback_base_url'])
            && $this->validHttpsOrLocalUrl($this->values['frontend_callback_url']);
    }

    private function validHttpsOrLocalUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return $scheme === 'https'
            || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true));
    }
}
