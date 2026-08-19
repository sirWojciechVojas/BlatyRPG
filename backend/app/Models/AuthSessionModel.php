<?php

namespace App\Models;

use CodeIgniter\Model;

final class AuthSessionModel extends Model
{
    protected $table = 'auth_sessions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'user_id', 'jti_hash', 'token_hash', 'expires_at', 'revoked_at',
        'last_seen_at', 'ip_hash', 'user_agent_hash',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
