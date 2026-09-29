<?php

namespace App\Models;

use CodeIgniter\Model;

class AudioLibraryModel extends Model
{
    protected $table = 'audio_libraries';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'name', 'scope', 'system_id', 'setting_id', 'owner_user_id', 'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
