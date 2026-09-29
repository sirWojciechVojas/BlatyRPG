<?php

namespace App\Models;

class MediaAssetModel extends BaseJsonModel
{
    public const PROVIDERS = ['external', 'cloudinary', 'r2'];
    public const VISIBILITIES = ['public', 'campaign', 'private'];
    public const STATUSES = [
        'pending', 'ready', 'failed', 'relocating', 'deleting', 'delete_failed', 'deleted',
    ];
    public const CATEGORIES = [
        'characters', 'npcs', 'monsters', 'items', 'maps', 'textures',
        'map-creator', 'audio', 'video', 'documents', 'other',
    ];

    protected $table = 'media_assets';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $allowedFields = [
        'owner_user_id', 'campaign_id', 'provider', 'provider_container',
        'provider_asset_id', 'public_id', 'resource_type', 'category', 'name',
        'description', 'tags', 'original_filename', 'mime_type', 'format',
        'file_size', 'width', 'height', 'duration', 'visibility', 'status',
        'revision', 'metadata', 'custom_metadata', 'upload_expires_at', 'source_url',
        'availability_status', 'availability_checked_at',
    ];
    protected $jsonFields = ['tags', 'metadata', 'custom_metadata'];
}
