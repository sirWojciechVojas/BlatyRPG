<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Config\Services;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
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
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            
            // Opcjonalnie: Przekaż ID usera do requestu, żeby kontroler wiedział kto pyta
            // $request->user_id = $decoded->sub; 

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
}