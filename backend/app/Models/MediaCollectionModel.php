<?php

namespace App\Models;

use CodeIgniter\Model;

final class MediaCollectionModel extends Model
{
    protected $table = 'media_collections';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['owner_user_id', 'name', 'description'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
}
