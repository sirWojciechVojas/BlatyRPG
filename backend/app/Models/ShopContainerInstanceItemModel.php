<?php

namespace App\Models;

use CodeIgniter\Model;

class ShopContainerInstanceItemModel extends Model
{
    protected $table = 'shop_container_instance_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'campaign_id',
        'container_id',
        'instance_id',
        'price_override',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
