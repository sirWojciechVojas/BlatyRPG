<?php

namespace App\Models;

class ShopItemDictionaryEntryModel extends BaseJsonModel
{
    protected $table = 'shop_item_dictionary_entries';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'campaign_id', 'group_code', 'code', 'label_pl', 'label_en',
        'applies_to_json', 'mechanics_json', 'is_active', 'sort_order',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $jsonFields = ['applies_to_json', 'mechanics_json'];
}
