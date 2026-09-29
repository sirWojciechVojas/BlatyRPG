<?php

namespace App\Models;

use CodeIgniter\Model;

class AudioPlaylistModel extends Model
{
    protected $table = 'audio_playlists';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['owner_user_id', 'name'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
