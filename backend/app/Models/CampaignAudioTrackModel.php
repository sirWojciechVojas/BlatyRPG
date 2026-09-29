<?php

namespace App\Models;

use CodeIgniter\Model;

class CampaignAudioTrackModel extends Model
{
    protected $table = 'campaign_audio_tracks';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'campaign_id', 'audio_track_id', 'added_by_user_id', 'is_enabled', 'sort_order',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
