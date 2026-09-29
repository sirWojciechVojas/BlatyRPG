<?php

namespace App\Models;

use CodeIgniter\Model;

class HandoutLibraryTagModel extends Model
{
    protected $table = 'handout_library_tags';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['owner_user_id', 'name'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
