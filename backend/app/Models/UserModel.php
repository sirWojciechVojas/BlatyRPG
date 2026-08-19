<?php

namespace App\Models;

use App\Services\Auth\UserRole;
use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'username', 'email', 'password_hash', 'role', 'avatar_url',
    ];
    protected bool $allowEmptyInserts = false;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $validationRules = [
        'username' => 'required|min_length[3]|max_length[100]|is_unique[users.username]',
        'email' => 'required|max_length[255]|valid_email|is_unique[users.email]',
        'password_hash' => 'required|min_length[12]|max_length[255]',
        'role' => 'permit_empty|in_list[user,player,gm,admin]',
    ];
    protected $validationMessages = [
        'email' => ['is_unique' => 'Ten adres email jest już zajęty.'],
        'username' => ['is_unique' => 'Ta nazwa użytkownika jest już zajęta.'],
    ];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;
    protected $allowCallbacks = true;
    protected $beforeInsert = ['normalizeRole', 'hashPassword'];
    protected $beforeUpdate = ['normalizeRole', 'hashPassword'];

    protected function hashPassword(array $data): array
    {
        if (!isset($data['data']['password_hash'])) {
            return $data;
        }
        $password = (string) $data['data']['password_hash'];
        if ((password_get_info($password)['algo'] ?? 0) === 0) {
            $data['data']['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        return $data;
    }

    protected function normalizeRole(array $data): array
    {
        if (isset($data['data']['role'])) {
            $data['data']['role'] = UserRole::normalize($data['data']['role']);
        }
        return $data;
    }
}
