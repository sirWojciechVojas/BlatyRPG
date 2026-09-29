<?php

namespace App\Models;

class JournalModel extends BaseJsonModel
{
    protected $table = 'journals';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'campaign_id', 'source_library_entry_id', 'author_user_id', 'kind', 'title',
        'content_json', 'search_text', 'tag_snapshot_json', 'folder_path_snapshot',
        'audience_mode', 'revision', 'published_at', 'deleted_at',
    ];
    protected $jsonFields = ['content_json', 'tag_snapshot_json'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
