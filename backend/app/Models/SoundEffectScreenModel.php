<?php

namespace App\Models;

use CodeIgniter\Model;

class SoundEffectScreenModel extends Model
{
    protected $table = 'sound_effect_screens';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'campaign_id', 'label', 'name', 'sort_order', 'grid_columns',
        'grid_rows', 'text_lines', 'pad_style',
        'created_by_user_id', 'updated_by_user_id',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
