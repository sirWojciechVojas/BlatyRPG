<?php

namespace App\Models;

class ShopProfileRevisionModel extends BaseJsonModel
{
    protected $table = 'shop_profile_revisions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'campaign_id',
        'shop_id',
        'changed_by',
        'change_type',
        'snapshot_json',
        'created_at',
    ];
    protected $useTimestamps = false;
    protected $jsonFields = ['snapshot_json'];
}
