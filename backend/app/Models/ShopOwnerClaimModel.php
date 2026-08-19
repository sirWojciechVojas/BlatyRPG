<?php

namespace App\Models;

use CodeIgniter\Model;

class ShopOwnerClaimModel extends Model
{
    protected $table = 'shop_owner_claims';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'campaign_id',
        'user_id',
        'character_id',
        'owner_code',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
