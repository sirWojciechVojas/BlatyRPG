<?php

namespace App\Services\Auth;

use CodeIgniter\HTTP\RequestInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/** Resolves the authenticated user from the application's signed JWT. */
class AuthContextService
{
    public function resolveFromRequest(RequestInterface $request): array
    {
        $header = (string) $request->getServer('HTTP_AUTHORIZATION');
        if ($header === '') {
            $header = $request->getHeaderLine('Authorization');
        }

        return $this->resolveFromAuthorizationHeader($header);
    }

    public function resolveFromAuthorizationHeader(?string $header): array
    {
        $context = $this->anonymousContext();
        if (!$header || !preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $matches)) {
            return $context;
        }

        $token = $matches[1];
        $secret = getenv('JWT_SECRET');
        if ($secret === false || trim((string) $secret) === '') {
            return $context;
        }

        try {
            $decoded = JWT::decode($token, new Key((string) $secret, 'HS256'));
            $userId = filter_var($decoded->sub ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($userId === false) {
                return $context;
            }

            $context['user_id'] = (int) $userId;
            $context['role'] = strtolower(trim((string) ($decoded->role ?? 'user')));
            $context['anonymous'] = false;
            $context['authenticated'] = true;
        } catch (\Throwable $exception) {
            // Authentication filters decide whether an anonymous context is allowed.
        }

        return $context;
    }

    public function isGmOrAdmin(array $authContext): bool
    {
        $role = strtolower((string) ($authContext['role'] ?? ''));
        return in_array($role, ['gm', 'admin'], true);
    }

    private function anonymousContext(): array
    {
        return [
            'user_id' => null,
            'role' => null,
            'token' => null,
            'anonymous' => true,
            'authenticated' => false,
        ];
    }
}
