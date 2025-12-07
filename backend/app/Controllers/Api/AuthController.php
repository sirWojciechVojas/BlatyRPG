<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\UserModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController extends ResourceController
{
    /**
     * POST /api/register
     */
    public function register()
    {
        $rules = [
            'username' => 'required|min_length[3]|is_unique[users.username]',
            'email'    => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[6]',
            'confirm_password' => 'matches[password]'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $model = new UserModel();
        
        // Mapujemy 'password' z formularza na 'password_hash' w bazie
        // Model sam to zahashuje dzięki callbackowi
        $data = [
            'username' => $this->request->getVar('username'),
            'email'    => $this->request->getVar('email'),
            'password_hash' => $this->request->getVar('password'),
            'role'     => 'user'
        ];

        if ($model->insert($data)) {
            return $this->respondCreated(['message' => 'Rejestracja udana. Możesz się zalogować.']);
        }

        return $this->failServerError('Błąd zapisu użytkownika.');
    }

    /**
     * POST /api/login
     */
    public function login()
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[6]'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $model = new UserModel();
        $user  = $model->where('email', $this->request->getVar('email'))->first();

        // 1. Czy user istnieje? 2. Czy hasło pasuje do hasha?
        if (!$user || !password_verify($this->request->getVar('password'), $user['password_hash'])) {
            return $this->failUnauthorized('Błędny email lub hasło.');
        }

        // Generowanie Tokena JWT
        $key = getenv('JWT_SECRET');
        $iat = time(); // Czas wydania
        $exp = $iat + getenv('JWT_TIME_TO_LIVE'); // Czas wygaśnięcia

        $payload = [
            'iss'  => 'BlatyRPG', // Wydawca
            'sub'  => $user['id'], // Subject (ID usera)
            'role' => $user['role'], // Rola
            'iat'  => $iat,
            'exp'  => $exp,
        ];

        $token = JWT::encode($payload, $key, 'HS256');

        return $this->respond([
            'status' => 'success',
            'token'  => $token,
            'user'   => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]
        ]);
    }
}