<?php

namespace App\Models;

use CodeIgniter\Model;

class UserAudioDeviceSettingModel extends Model
{
    protected $table = 'user_audio_device_settings';
    protected $primaryKey = 'user_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'user_id',
        'microphone_device_id',
        'output_device_id',
        'external_input_1_device_id',
        'external_input_2_device_id',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
