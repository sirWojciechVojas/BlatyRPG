<?php

namespace App\Models;

class CampaignModel extends BaseJsonModel
{
    protected $table = 'campaigns';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $allowedFields = [
        'game_master_id',
        'name',
        'description',
        'banner_url',
        'system_type',
        'settings_json',
        'is_active',
        'status',
        'last_activity_at',
    ];
    protected $jsonFields = ['settings_json'];
}
