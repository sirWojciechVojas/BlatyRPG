<?php

namespace App\Models;

use CodeIgniter\Model;

class CharacterAssetModel extends Model
{
    public const TYPES = ['avatar', 'portrait', 'token', 'fullbody'];

    protected $table = 'character_assets';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['asset_set_id', 'type', 'public_id'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $validationRules = [
        'asset_set_id' => 'required|integer',
        'type' => 'required|in_list[avatar,portrait,token,fullbody]',
        'public_id' => 'required|max_length[255]',
    ];
}
