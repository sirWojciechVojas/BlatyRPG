<?php

namespace App\Models;

use CodeIgniter\Model;

class CampaignCalendarEventModel extends Model
{
    protected $table = 'campaign_calendar_events';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'campaign_id', 'title', 'description', 'event_type',
        'start_year', 'start_day_of_year', 'start_minute',
        'end_year', 'end_day_of_year', 'end_minute', 'color', 'visibility',
        'all_day', 'repeat_yearly', 'revision', 'created_by_user_id',
        'updated_by_user_id',
    ];
}
