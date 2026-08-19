<?php

namespace App\Models;

class ShopItemInstanceModel extends BaseJsonModel
{
    protected $table = 'shop_item_instances';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'campaign_id',
        'template_id',
        'name_override',
        'data_override_json',
        'note',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $jsonFields = ['data_override_json'];
}
