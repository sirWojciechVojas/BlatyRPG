<?php

namespace App\Models;

use CodeIgniter\Model;

class CampaignCalendarModel extends Model
{
    protected $table = 'campaign_calendars';
    protected $primaryKey = 'campaign_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'campaign_id', 'calendar_key', 'year', 'day_of_year', 'minute_of_day',
        'show_time', 'is_running', 'revision', 'updated_by_user_id',
    ];
}
