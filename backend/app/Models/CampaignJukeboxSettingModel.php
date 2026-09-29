<?php

namespace App\Models;

class CampaignJukeboxSettingModel extends BaseJsonModel
{
    protected $table = 'campaign_jukebox_settings';
    protected $primaryKey = 'campaign_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $allowedFields = [
        'campaign_id', 'state_json', 'settings_json', 'revision', 'updated_by_user_id',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $jsonFields = ['state_json', 'settings_json'];
}
