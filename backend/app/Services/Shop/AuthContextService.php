<?php

namespace App\Services\Shop;

use CodeIgniter\HTTP\RequestInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthContextService
{
    public function resolveFromAuthorizationHeader(?string $header): array
    {
        if (!$header) {
            return [
                'user_id' => null,
                'role' => null,
                'token' => null,
                'anonymous' => true,
            ];
        }

        if (!preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            return [
                'user_id' => null,
                'role' => null,
                'token' => null,
            ];
        }

        $token = $matches[1];
        $secret = getenv('JWT_SECRET');
        if (!$secret) {
            return [
                'user_id' => null,
                'role' => null,
                'token' => $token,
            ];
        }

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return [
                'user_id' => isset($decoded->sub) ? (int) $decoded->sub : null,
                'role' => isset($decoded->role) ? (string) $decoded->role : null,
                'token' => $token,
            ];
        } catch (\Throwable $e) {
            return [
                'user_id' => null,
                'role' => null,
                'token' => $token,
            ];
        }
    }

    public function resolveFromRequest(RequestInterface $request): array
    {
        $auth = $this->resolveFromAuthorizationHeader(
            (string) $request->getServer('HTTP_AUTHORIZATION')
        );
        $auth['character_view'] = strtolower(trim(
            $request->getHeaderLine('X-Shop-View-Mode')
        )) === 'character';

        if (!$this->isDevelopmentSelectorEnabled()) {
            return $auth;
        }

        $mode = strtolower(trim($request->getHeaderLine('X-Shop-Access-Mode')));
        if (!in_array($mode, ['gm', 'player'], true)) {
            return $auth;
        }

        $ownerCode = strtoupper(trim($request->getHeaderLine('X-Shop-Owner-Code')));
        $characterId = filter_var(
            $request->getHeaderLine('X-Shop-Character-Id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($mode === 'player' && !preg_match('/^[A-Z0-9_-]{1,64}$/', $ownerCode)) {
            return $auth;
        }

        return [
            'user_id' => null,
            'role' => $mode === 'gm' ? 'gm' : 'user',
            'token' => $auth['token'] ?? null,
            'anonymous' => true,
            'development_access' => true,
            'access_mode' => $mode,
            'character_view' => !empty($auth['character_view']),
            'selected_owner_codes' => $ownerCode ? [$ownerCode] : [],
            'character_id' => $characterId !== false ? (int) $characterId : null,
        ];
    }

    public function isGmOrAdmin(array $authContext): bool
    {
        $role = strtolower((string) ($authContext['role'] ?? ''));
        return in_array($role, ['gm', 'admin'], true);
    }

    public function isDevelopmentSelectorEnabled(): bool
    {
        if (strtolower((string) getenv('CI_ENVIRONMENT')) === 'production') {
            return false;
        }

        $flag = getenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS');
        if ($flag !== false && $flag !== '') {
            return in_array(strtolower((string) $flag), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
