<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use CodeIgniter\RESTful\ResourceController;
use Firebase\JWT\JWT;

class AuthController extends ResourceController
{
    /**
     * POST /api/register
     */
    public function register()
    {
        $rules = [
            'username' => 'required|min_length[3]|is_unique[users.username]',
            'email' => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[5]',
            'confirm_password' => 'matches[password]',
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $model = new UserModel();

        $data = [
            'username' => $this->request->getVar('username'),
            'email' => $this->request->getVar('email'),
            'password_hash' => $this->request->getVar('password'),
            'role' => 'user',
        ];

        if ($model->insert($data)) {
            return $this->respondCreated(['message' => 'Rejestracja udana. Mozesz sie zalogowac.']);
        }

        return $this->failServerError('Blad zapisu uzytkownika.');
    }

    /**
     * POST /api/auth/login
     * Supported identifiers: email, login, username.
     */
    public function login()
    {
        $payload = [];
        $contentType = strtolower($this->request->getHeaderLine('Content-Type'));
        $isJson = strpos($contentType, 'application/json') !== false;

        if ($isJson) {
            try {
                $decoded = $this->request->getJSON(true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            } catch (\Throwable $e) {
                return $this->failValidationErrors([
                    'body' => 'Invalid JSON payload.',
                ]);
            }
        }

        $email = trim((string) ($payload['email'] ?? $this->request->getVar('email') ?? ''));
        $login = trim((string) ($payload['login'] ?? $this->request->getVar('login') ?? ''));
        $username = trim((string) ($payload['username'] ?? $this->request->getVar('username') ?? ''));
        $password = (string) ($payload['password'] ?? $this->request->getVar('password') ?? '');

        $identifier = $email !== '' ? $email : ($login !== '' ? $login : $username);

        $errors = [];
        if ($identifier === '') {
            $errors['identifier'] = 'The email, login or username field is required.';
        }
        if ($password === '') {
            $errors['password'] = 'The password field is required.';
        } elseif (strlen($password) < 5) {
            $errors['password'] = 'The password field must be at least 5 characters in length.';
        }

        if ($errors) {
            return $this->failValidationErrors($errors);
        }

        $model = new UserModel();
        $user = $model
            ->groupStart()
            ->where('email', $identifier)
            ->orWhere('username', $identifier)
            ->groupEnd()
            ->first();

        if (!$user) {
            return $this->failUnauthorized('Nieprawidlowy login lub haslo.');
        }

        $storedHash = trim((string) ($user['password_hash'] ?? ''));
        if ($storedHash === '' || !password_verify($password, $storedHash)) {
            return $this->failUnauthorized('Nieprawidlowy login lub haslo.');
        }

        // Rehash only for supported password_hash outputs.
        if (password_get_info($storedHash)['algo'] !== 0
            && password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
            $model->update((int) $user['id'], [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        }

        $key = getenv('JWT_SECRET');
        $iat = time();
        $ttl = (int) getenv('JWT_TIME_TO_LIVE');
        $exp = $iat + $ttl;

        $payload = [
            'iss' => 'BlatyRPG',
            'sub' => $user['id'],
            'role' => $user['role'],
            'iat' => $iat,
            'exp' => $exp,
        ];

        $token = JWT::encode($payload, $key, 'HS256');

        return $this->respond([
            'status' => 'success',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'login' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ]);
    }

}
