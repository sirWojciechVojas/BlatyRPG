<?php

namespace App\Models;

class ShopTradeTransactionModel extends BaseJsonModel
{
    protected $table = 'shop_trade_transactions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'campaign_id',
        'transaction_uuid',
        'idempotency_key',
        'actor_user_id',
        'actor_id',
        'actor_name',
        'owner_code',
        'shop_id',
        'shop_name',
        'seller_id',
        'seller_name',
        'buyer_id',
        'buyer_name',
        'transaction_type',
        'status',
        'error_code',
        'item_id',
        'item_template_id',
        'item_name',
        'quantity',
        'base_price',
        'final_price',
        'currency',
        'price_modifiers_json',
        'conditions_snapshot_json',
        'before_snapshot_json',
        'after_snapshot_json',
        'parent_transaction_id',
        'correction_reason',
        'gm_note',
        'performed_by',
        'reversed_by',
        'redone_by',
        'history_json',
        'total_brass',
        'payload_json',
        'response_json',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    protected $jsonFields = [
        'payload_json',
        'response_json',
        'price_modifiers_json',
        'conditions_snapshot_json',
        'before_snapshot_json',
        'after_snapshot_json',
        'history_json',
    ];
}
