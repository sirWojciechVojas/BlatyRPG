<?php

namespace App\Models;

use CodeIgniter\Model;

class ResourcePermissionModel extends Model
{
    protected $table = 'resource_permissions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'campaign_id', 'resource_type', 'resource_id', 'user_id',
        'access_level', 'granted_by_user_id',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
