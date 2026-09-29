<?php

namespace App\Models;

use CodeIgniter\Model;

class AudioPlaylistItemModel extends Model
{
    protected $table = 'audio_playlist_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['playlist_id', 'audio_track_id', 'sort_order'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
