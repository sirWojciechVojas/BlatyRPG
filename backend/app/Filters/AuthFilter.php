<?php

namespace App\Filters;

use App\Services\Auth\AuthContextService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if ($this->shouldBypassShopAuth($request)) {
            return null;
        }
        try {
            $auth = (new AuthContextService())->resolveFromRequest($request);
        } catch (\Throwable $exception) {
            return $this->error('Authentication is temporarily unavailable.', 503);
        }
        if (($auth['authentication_error'] ?? null) === 'configuration_error') {
            return $this->error('Authentication is temporarily unavailable.', 503);
        }
        if (empty($auth['authenticated']) || !empty($auth['anonymous'])) {
            return $this->error('Invalid or expired access token.', 401);
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    private function error(string $message, int $status): ResponseInterface
    {
        return Services::response()->setStatusCode($status)->setJSON([
            'code' => $status === 503 ? 'auth_unavailable' : 'invalid_token',
            'message' => $message,
        ]);
    }

    private function shouldBypassShopAuth(RequestInterface $request): bool
    {
        if (strtolower((string) getenv('CI_ENVIRONMENT')) === 'production') {
            return false;
        }
        $flag = getenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS');
        $enabled = $flag !== false && $flag !== ''
            && in_array(strtolower((string) $flag), ['1', 'true', 'yes', 'on'], true);
        if (!$enabled) {
            return false;
        }
        $paths = [
            $request->getUri()->getPath(),
            (string) $request->getServer('REQUEST_URI'),
            (string) $request->getServer('PATH_INFO'),
        ];
        foreach ($paths as $path) {
            $path = trim((string) parse_url((string) $path, PHP_URL_PATH), '/');
            if ($path === 'api/shop' || strpos($path, 'api/shop/') !== false) {
                return true;
            }
        }
        return false;
    }
}
