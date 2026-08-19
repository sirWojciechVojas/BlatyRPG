<?php

namespace App\Models;

class ShopTemplateModel extends BaseJsonModel
{
    protected $table = 'shop_templates';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'campaign_id',
        'name',
        'description',
        'details',
        'item_class',
        'item_id',
        'item_genre',
        'img_class',
        'prize',
        'currency_code',
        'charge',
        'draft',
        'weapon_json',
        'attributes_json',
        'mechanics_json',
        'mechanics_mode',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $jsonFields = ['weapon_json', 'attributes_json', 'mechanics_json'];
}
