<?php

namespace App\Models;

use CodeIgniter\Model;

class ShopOwnerWalletBalanceModel extends Model
{
    protected $table = 'shop_owner_wallet_balances';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'campaign_id',
        'owner_code',
        'currency_code',
        'balance',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
