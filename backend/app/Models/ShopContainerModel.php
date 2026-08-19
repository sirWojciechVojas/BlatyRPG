<?php

namespace App\Models;

use CodeIgniter\Model;

class ShopContainerModel extends Model
{
    protected $table = 'shop_containers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'campaign_id',
        'shop_id',
        'container_type',
        'system_key',
        'owner_code',
        'name',
        'capacity',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
