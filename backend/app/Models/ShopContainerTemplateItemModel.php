<?php

namespace App\Models;

use CodeIgniter\Model;

class ShopContainerTemplateItemModel extends Model
{
    protected $table = 'shop_container_template_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'campaign_id',
        'container_id',
        'template_id',
        'quantity',
        'price_override',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
