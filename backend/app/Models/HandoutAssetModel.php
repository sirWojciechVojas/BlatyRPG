<?php

namespace App\Models;

use CodeIgniter\Model;

class HandoutAssetModel extends Model
{
    protected $table = 'handout_assets';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'owner_user_id', 'storage_key', 'original_name', 'mime_type', 'byte_size',
        'sha256', 'deleted_at',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
