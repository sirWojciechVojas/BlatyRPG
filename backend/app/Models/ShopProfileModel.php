<?php

namespace App\Models;

class ShopProfileModel extends BaseJsonModel
{
    protected $table = 'shop_profiles';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'campaign_id',
        'shop_id',
        'type_id',
        'signboard_name',
        'owner_code',
        'owner_name',
        'signboard_alt_names_json',
        'category_tags_json',
        'world_profile_id',
        'location_type',
        'legal_status',
        'wealth_tier',
        'reputation',
        'seasonality',
        'counterfeit_risk',
        'pricing_config_json',
        'market_settings_json',
        'market_events_json',
        'custom_presets_json',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $jsonFields = [
        'signboard_alt_names_json',
        'category_tags_json',
        'pricing_config_json',
        'market_settings_json',
        'market_events_json',
        'custom_presets_json',
    ];
}
