<?php

namespace App\Models;

use CodeIgniter\Model;

class ShopOwnerWalletModel extends Model
{
    protected $table = 'shop_owner_wallets';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'campaign_id',
        'owner_code',
        'brass_balance',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
