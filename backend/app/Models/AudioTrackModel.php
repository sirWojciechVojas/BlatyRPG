<?php

namespace App\Models;

class AudioTrackModel extends BaseJsonModel
{
    protected $table = 'audio_tracks';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'library_id', 'owner_user_id', 'title', 'category', 'source_type',
        'provider', 'provider_reference', 'storage_key', 'original_name',
        'external_url', 'mime_type', 'extension', 'byte_size', 'sha256',
        'duration_seconds', 'loop_enabled', 'tags_json', 'thumbnail_url', 'status',
        'media_asset_id',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $jsonFields = ['tags_json'];
}
