<?php

namespace App\Models;

use CodeIgniter\Model;

class HandoutLibraryFolderModel extends Model
{
    protected $table = 'handout_library_folders';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['owner_user_id', 'parent_id', 'name', 'sort_order', 'deleted_at'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
