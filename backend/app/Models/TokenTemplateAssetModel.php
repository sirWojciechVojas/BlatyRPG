<?php

namespace App\Models;

use CodeIgniter\Model;

class TokenTemplateAssetModel extends Model
{
    protected $table = 'token_template_assets';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'storage_key', 'original_name', 'mime_type', 'byte_size',
        'width', 'height', 'created_by_user_id', 'media_asset_id', 'created_at',
    ];
    protected $useTimestamps = false;
}
