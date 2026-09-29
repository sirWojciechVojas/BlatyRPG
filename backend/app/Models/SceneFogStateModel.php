<?php

namespace App\Models;

use CodeIgniter\Model;

class SceneFogStateModel extends Model
{
    protected $table = 'scene_fog_states';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'campaign_id', 'scene_id', 'user_id', 'cell_size',
        'explored_ranges_json', 'forced_hidden_ranges_json', 'revision',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
