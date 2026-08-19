<?php

namespace App\Models;

class ShopWorldProfileModel extends BaseJsonModel
{
    protected $table = 'shop_world_profiles';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'id',
        'label_pl',
        'label_en',
        'description',
        'impact_summary_pl',
        'modifiers_json',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $jsonFields = ['modifiers_json'];
}
