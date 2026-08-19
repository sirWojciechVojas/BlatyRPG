<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'username', 'email', 'password_hash', 'role', 'avatar_url'
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'username'      => 'required|min_length[3]|is_unique[users.username]',
        'email'         => 'required|valid_email|is_unique[users.email]',
        'password_hash' => 'required|min_length[6]',
    ];
    protected $validationMessages   = [
        'email' => [
            'is_unique' => 'Ten adres email jest już zajęty.',
        ],
        'username' => [
            'is_unique' => 'Ta nazwa użytkownika jest już zajęta.',
        ]
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['hashPassword'];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

     /**
     * Automatycznie hashuje hasło przed zapisem do bazy
     */
    protected function hashPassword(array $data)
    {
        if (! isset($data['data']['password_hash'])) {
            return $data;
        }

        // Szyfrowanie algorytmem domyślnym (zazwyczaj Bcrypt/Argon2)
        $data['data']['password_hash'] = password_hash($data['data']['password_hash'], PASSWORD_DEFAULT);

        return $data;
    }
}
