<?php

namespace App\Models;

class ShopCatalogNodeModel extends BaseJsonModel
{
    protected $table = 'shop_catalog_nodes';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'node_key',
        'parent_key',
        'level',
        'name_pl',
        'name_en',
        'description_pl',
        'payload_json',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $jsonFields = ['payload_json'];
}
