<?php

namespace App\Models;

class ShopSuggestionCacheModel extends BaseJsonModel
{
    protected $table = 'shop_suggestion_cache';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'campaign_id',
        'shop_id',
        'profile_hash',
        'suggestions_json',
        'recommendations_json',
        'generated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $jsonFields = ['suggestions_json', 'recommendations_json'];
}
