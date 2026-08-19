<?php

namespace App\Models;

class ShopIconMetadataModel extends BaseJsonModel
{
    protected $table = 'shop_icon_metadata';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'campaign_id', 'icon_class', 'name', 'source_name', 'image_path', 'image_path_small', 'image_path_large', 'description', 'special_marks',
        'type_keys_json', 'subtype_keys_json', 'item_classes_json', 'item_genres_json',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $jsonFields = [
        'type_keys_json', 'subtype_keys_json', 'item_classes_json', 'item_genres_json',
    ];
}
