<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\Auth\AuthContextService;
use App\Services\Auth\AuthUserPresenter;
use CodeIgniter\API\ResponseTrait;

class AuthSessionController extends BaseController
{
    use ResponseTrait;

    public function me()
    {
        $auth = (new AuthContextService())->resolveFromRequest($this->request);
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) {
            return $this->failUnauthorized('Authentication is required.');
        }

        $user = (new UserModel())
            ->where('id', $userId)
            ->where('deleted_at', null)
            ->first();
        if (!$user) {
            return $this->failUnauthorized('Authentication is required.');
        }

        return $this->respond(['user' => (new AuthUserPresenter())->present($user)]);
    }
}
