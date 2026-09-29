<?php

namespace App\Models;

use CodeIgniter\Model;

class CampaignCalendarMoonOverrideModel extends Model
{
    protected $table = 'campaign_calendar_moon_overrides';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'campaign_id', 'year', 'day_of_year', 'phase_key', 'revision',
        'updated_by_user_id',
    ];
}
