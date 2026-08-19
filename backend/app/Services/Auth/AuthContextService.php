<?php

namespace App\Services\Auth;

use CodeIgniter\HTTP\RequestInterface;

/** Resolves a revocable JWT session and its current database principal. */
class AuthContextService
{
    private $sessions;

    public function __construct(?AuthSessionService $sessions = null)
    {
        $this->sessions = $sessions ?: new AuthSessionService();
    }

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
        $context = $this->sessions->resolveAuthorizationHeader($header);
        $context['token'] = null;
        return $context;
    }

    public function isGmOrAdmin(array $authContext): bool
    {
        return in_array(
            UserRole::normalize($authContext['role'] ?? ''),
            [UserRole::GM, UserRole::ADMIN],
            true
        );
    }
}
