<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if ($this->shouldBypassShopAuth($request)) {
            return null;
        }

        // Pobierz nagłówek Authorization
        $header = $request->getServer('HTTP_AUTHORIZATION');

        if (!$header) {
            return Services::response()
                ->setJSON(['error' => 'Brak tokena dostępu'])
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
        }

        // Format nagłówka: "Bearer <token>"
        $token = null;
        if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            return Services::response()
                ->setJSON(['error' => 'Niepoprawny format tokena'])
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
        }

        try {
            $key = getenv('JWT_SECRET');
            // Dekodowanie i weryfikacja
            JWT::decode($token, new Key($key, 'HS256'));
        } catch (\Exception $e) {
            return Services::response()
                ->setJSON(['error' => 'Token nieważny lub wygasł'])
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nic nie rób po
    }

    private function shouldBypassShopAuth(RequestInterface $request): bool
    {
        if (strtolower((string) getenv('CI_ENVIRONMENT')) === 'production') {
            return false;
        }

        $flag = getenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS');
        $enabled = $flag !== false
            && $flag !== ''
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
