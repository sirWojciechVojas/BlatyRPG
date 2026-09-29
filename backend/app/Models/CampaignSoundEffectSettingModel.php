<?php

namespace App\Models;

use CodeIgniter\Model;

class CampaignSoundEffectSettingModel extends Model
{
    protected $table = 'campaign_sound_effect_settings';
    protected $primaryKey = 'campaign_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $allowedFields = ['campaign_id', 'revision', 'updated_by_user_id'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
