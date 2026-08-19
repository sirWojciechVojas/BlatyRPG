<?php

namespace App\Models;

class CampaignMemberModel extends BaseJsonModel
{
    protected $table = 'campaign_members';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'campaign_id',
        'user_id',
        'role',
        'permissions_json',
        'is_active',
        'joined_at',
        'left_at',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $jsonFields = ['permissions_json'];
}
