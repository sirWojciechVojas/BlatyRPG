<?php

namespace App\Models;

use CodeIgniter\Model;

class CharacterAssetSetModel extends Model
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_DISABLED = 'disabled';
    public const STATUSES = [self::STATUS_AVAILABLE, self::STATUS_ASSIGNED, self::STATUS_DISABLED];

    protected $table = 'character_asset_sets';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'status'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $validationRules = [
        'name' => 'permit_empty|max_length[150]',
        'status' => 'required|in_list[available,assigned,disabled]',
    ];
}
