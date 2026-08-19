<?php

namespace App\Models;

use CodeIgniter\Model;

class ShopTypeModel extends Model
{
    protected $table = 'shop_types';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'slug',
        'name',
        'category',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
