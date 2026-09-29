<?php

namespace App\Models;

class HandoutLibraryEntryModel extends BaseJsonModel
{
    protected $table = 'handout_library_entries';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'owner_user_id', 'folder_id', 'title', 'content_json', 'search_text',
        'revision', 'deleted_at',
    ];
    protected $jsonFields = ['content_json'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
