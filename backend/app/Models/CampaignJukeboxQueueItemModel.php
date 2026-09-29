<?php

namespace App\Models;

use CodeIgniter\Model;

class CampaignJukeboxQueueItemModel extends Model
{
    protected $table = 'campaign_jukebox_queue_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'campaign_id', 'channel_id', 'audio_track_id', 'playlist_id',
        'added_by_user_id', 'sort_order',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
