<?php

namespace App\Services\Media;

use App\Models\MediaAssetModel;
use CodeIgniter\Database\BaseConnection;

final class MediaService
{
    private const DEFAULT_MAX_BYTES = 5 * 1024 * 1024 * 1024;

    private $db;
    private $assets;
    private $selector;
    private $access;
    private $providers;

    public function __construct(
        ?BaseConnection $db = null,
        ?MediaProviderSelector $selector = null,
        ?array $providers = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->assets = new MediaAssetModel($this->db);
        $this->selector = $selector ?: new MediaProviderSelector();
        $this->access = new MediaAccessPolicy($this->db);
        $providers = $providers ?: [
            new CloudinaryMediaProvider(), new R2MediaProvider(), new ExternalMediaProvider(),
        ];
        $this->providers = [];
        foreach ($providers as $provider) {
            if (!$provider instanceof MediaProviderInterface) {
                throw new \InvalidArgumentException('Every media provider must implement MediaProviderInterface.');
            }
            $this->providers[$provider->name()] = $provider;
        }
    }

    public function initiateUpload(array $auth, array $input): array
    {
        $this->assertSchema();
        $visibility = strtolower(trim((string) ($input['visibility'] ?? 'private')));
        if (!in_array($visibility, MediaAssetModel::VISIBILITIES, true)) {
            throw new MediaException('validation_failed', 'Media visibility is invalid.', 422);
        }
        $campaignId = $this->positiveId($input['campaignId'] ?? $input['campaign_id'] ?? null);
        if ($visibility === 'campaign' && $campaignId === null) {
            throw new MediaException('validation_failed', 'Campaign media requires a campaign ID.', 422);
        }
        if ($visibility !== 'campaign') {
            $campaignId = null;
        }
        $user = $this->access->assertUploadAllowed($auth, $visibility, $campaignId);
        $category = $this->category((string) ($input['category'] ?? 'other'));
        $mimeType = strtolower(trim((string) ($input['mimeType'] ?? $input['mime_type'] ?? '')));
        if (!preg_match('#^[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+*-]+$#i', $mimeType)) {
            throw new MediaException('validation_failed', 'MIME type is invalid.', 422);
        }
        $fileSize = filter_var($input['fileSize'] ?? $input['file_size'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => $this->maxUploadBytes()],
        ]);
        if ($fileSize === false) {
            throw new MediaException('validation_failed', 'File size is invalid or exceeds the upload limit.', 422);
        }
        $filename = $this->filename((string) ($input['filename'] ?? $input['originalFilename'] ?? 'upload'));
        $resourceType = $this->resourceType($mimeType);
        $providerName = $this->selector->select($category, $mimeType, (int) $fileSize);
        $provider = $this->provider($providerName);
        $storageId = $this->storageIdentifier($providerName, $category, $filename, $user['id']);
        $expiresAt = date('Y-m-d H:i:s', time() + 900);
        $asset = [
            'owner_user_id' => $user['id'],
            'campaign_id' => $campaignId,
            'provider' => $providerName,
            'provider_container' => '',
            'provider_asset_id' => $providerName === 'r2' ? $storageId : null,
            'public_id' => $providerName === 'cloudinary' ? $storageId : null,
            'resource_type' => $resourceType,
            'category' => $category,
            'name' => $this->displayName((string) ($input['name'] ?? ''), $filename),
            'description' => null,
            'tags' => [],
            'original_filename' => $filename,
            'mime_type' => $mimeType,
            'format' => $this->format($filename, $mimeType),
            'file_size' => (int) $fileSize,
            'visibility' => $visibility,
            'status' => 'pending',
            'revision' => 1,
            'metadata' => ['authorized_file_size' => (int) $fileSize],
            'custom_metadata' => [],
            'upload_expires_at' => $expiresAt,
        ];
        $asset['provider_container'] = $provider->storageContainer($asset);
        $upload = $provider->prepareUpload($asset);
        if (!$this->assets->insert($asset)) {
            throw new MediaException('media_write_failed', 'Could not register the media upload.', 500, $this->assets->errors());
        }
        $asset['id'] = (int) $this->assets->getInsertID();
        return [
            'asset' => $this->presentTrusted($asset),
            'upload' => $upload,
        ];
    }

    public function completeUpload(array $auth, int $assetId, array $payload): array
    {
        $this->assertSchema();
        $asset = $this->find($assetId);
        $this->access->assertCanManage($auth, $asset);
        if (($asset['status'] ?? '') !== 'pending') {
            throw new MediaException('media_upload_not_pending', 'This media upload is no longer pending.', 409);
        }
        $expiresAt = strtotime((string) ($asset['upload_expires_at'] ?? ''));
        if ($expiresAt !== false && $expiresAt < time()) {
            throw new MediaException('media_upload_expired', 'The media upload authorization has expired.', 409);
        }
        $result = $this->provider((string) $asset['provider'])->completeUpload($asset, $payload);
        $expectedSize = (int) ($asset['file_size'] ?? 0);
        $actualSize = (int) ($result['file_size'] ?? 0);
        if ($actualSize < 1 || $actualSize > $this->maxUploadBytes()
            || ($expectedSize > 0 && $actualSize !== $expectedSize)) {
            throw new MediaException('upload_size_mismatch', 'Uploaded file size does not match the authorized size.', 422);
        }
        $metadata = is_array($asset['metadata'] ?? null) ? $asset['metadata'] : [];
        $metadata = array_merge($metadata, is_array($result['metadata'] ?? null) ? $result['metadata'] : []);
        $update = [
            'provider_container' => $result['provider_container'] ?? $asset['provider_container'],
            'provider_asset_id' => $result['provider_asset_id'] ?? $asset['provider_asset_id'],
            'public_id' => $result['public_id'] ?? $asset['public_id'],
            'mime_type' => $result['mime_type'] ?? $asset['mime_type'],
            'file_size' => $actualSize,
            'width' => $result['width'] ?? null,
            'height' => $result['height'] ?? null,
            'duration' => $result['duration'] ?? null,
            'metadata' => $metadata,
            'status' => 'ready',
            'upload_expires_at' => null,
        ];
        if (!$this->assets->update($assetId, $update)) {
            throw new MediaException('media_write_failed', 'Could not finalize the media upload.', 500, $this->assets->errors());
        }
        return $this->presentTrusted(array_merge($asset, $update));
    }

    /** Stores a file already present on the application server through the selected provider. */
    public function uploadFile(array $auth, string $path, array $input): array
    {
        $this->assertSchema();
        if (!is_file($path) || !is_readable($path)) {
            throw new MediaException('media_source_unavailable', 'The source file cannot be read.', 422);
        }
        $visibility = strtolower(trim((string) ($input['visibility'] ?? 'private')));
        if (!in_array($visibility, MediaAssetModel::VISIBILITIES, true)) {
            throw new MediaException('validation_failed', 'Media visibility is invalid.', 422);
        }
        $campaignId = $this->positiveId($input['campaignId'] ?? $input['campaign_id'] ?? null);
        if ($visibility === 'campaign' && $campaignId === null) {
            throw new MediaException('validation_failed', 'Campaign media requires a campaign ID.', 422);
        }
        if ($visibility !== 'campaign') {
            $campaignId = null;
        }
        $user = $this->access->assertUploadAllowed($auth, $visibility, $campaignId);
        $filename = $this->filename((string) ($input['filename'] ?? $input['originalFilename'] ?? basename($path)));
        $mimeType = strtolower(trim((string) ($input['mimeType'] ?? $input['mime_type'] ?? '')));
        if ($mimeType === '') {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = strtolower((string) $finfo->file($path));
        }
        if (!preg_match('#^[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+*-]+$#i', $mimeType)) {
            throw new MediaException('validation_failed', 'MIME type is invalid.', 422);
        }
        $fileSize = (int) filesize($path);
        if ($fileSize < 1 || $fileSize > $this->maxUploadBytes()) {
            throw new MediaException('validation_failed', 'File size is invalid or exceeds the upload limit.', 422);
        }
        $category = $this->category((string) ($input['category'] ?? 'other'));
        $resourceType = $this->resourceType($mimeType);
        $providerName = $this->selector->select($category, $mimeType, $fileSize);
        $provider = $this->provider($providerName);
        $storageId = $this->storageIdentifier($providerName, $category, $filename, $user['id']);
        $dimensions = strpos($mimeType, 'image/') === 0 ? @getimagesize($path) : false;
        $asset = [
            'owner_user_id' => $this->positiveId($input['ownerUserId'] ?? $input['owner_user_id'] ?? null) ?? $user['id'],
            'campaign_id' => $campaignId,
            'provider' => $providerName,
            'provider_container' => '',
            'provider_asset_id' => $providerName === 'r2' ? $storageId : null,
            'public_id' => $providerName === 'cloudinary' ? $storageId : null,
            'resource_type' => $resourceType,
            'category' => $category,
            'name' => $this->displayName((string) ($input['name'] ?? ''), $filename),
            'description' => isset($input['description']) ? trim((string) $input['description']) : null,
            'tags' => is_array($input['tags'] ?? null) ? array_values($input['tags']) : [],
            'original_filename' => $filename,
            'mime_type' => $mimeType,
            'format' => $this->format($filename, $mimeType),
            'file_size' => $fileSize,
            'width' => $dimensions ? (int) $dimensions[0] : null,
            'height' => $dimensions ? (int) $dimensions[1] : null,
            'duration' => null,
            'visibility' => $visibility,
            'status' => 'pending',
            'revision' => 1,
            'metadata' => ['server_upload' => true],
            'custom_metadata' => is_array($input['customMetadata'] ?? null) ? $input['customMetadata'] : [],
            'upload_expires_at' => null,
        ];
        $asset['provider_container'] = $provider->storageContainer($asset);
        if (!$this->assets->insert($asset)) {
            throw new MediaException('media_write_failed', 'Could not register the media upload.', 500, $this->assets->errors());
        }
        $asset['id'] = (int) $this->assets->getInsertID();
        try {
            $remote = $provider->uploadFile($asset, $path);
            $metadata = array_merge($asset['metadata'], is_array($remote['metadata'] ?? null) ? $remote['metadata'] : []);
            $update = [
                'provider_container' => $remote['provider_container'] ?? $asset['provider_container'],
                'provider_asset_id' => $remote['provider_asset_id'] ?? $asset['provider_asset_id'],
                'public_id' => $remote['public_id'] ?? $asset['public_id'],
                'mime_type' => $remote['mime_type'] ?? $asset['mime_type'],
                'file_size' => $remote['file_size'] ?? $asset['file_size'],
                'width' => $remote['width'] ?? $asset['width'],
                'height' => $remote['height'] ?? $asset['height'],
                'duration' => $remote['duration'] ?? null,
                'metadata' => $metadata,
                'status' => 'ready',
            ];
            if (!$this->assets->update($asset['id'], $update)) {
                throw new MediaException('media_write_failed', 'Could not finalize the media upload.', 500, $this->assets->errors());
            }
            return $this->presentTrusted(array_merge($asset, $update));
        } catch (\Throwable $exception) {
            $this->assets->update($asset['id'], ['status' => 'failed']);
            throw $exception;
        }
    }

    public function verify(int $assetId): array
    {
        $asset = $this->find($assetId);
        return $this->provider((string) $asset['provider'])->verifyObject($asset);
    }

    /** Provider purge primitive. Callers must check domain relations first. */
    public function purge(int $assetId): array
    {
        $asset = $this->findIncludingFailedDelete($assetId);
        $this->assets->update($assetId, ['status' => 'deleting']);
        try {
            $this->provider((string) $asset['provider'])->deleteObject($asset);
            $this->assets->update($assetId, ['status' => 'deleted']);
            $this->assets->delete($assetId);
            return ['id' => $assetId, 'status' => 'deleted'];
        } catch (\Throwable $exception) {
            $this->assets->update($assetId, ['status' => 'delete_failed']);
            if ($exception instanceof MediaException) {
                throw $exception;
            }
            throw new MediaException('media_provider_unavailable', 'The provider object could not be deleted.', 503);
        }
    }

    public function findAsset(int $assetId): array
    {
        return $this->find($assetId);
    }

    public function get(array $auth, int $assetId, ?string $variant = null): array
    {
        $asset = $this->find($assetId);
        $this->access->assertCanView($auth, $asset);
        return $this->presentTrusted($asset, $variant);
    }

    /** Used only after a module has performed its own domain authorization. */
    public function getTrusted(int $assetId, ?string $variant = null): array
    {
        return $this->presentTrusted($this->find($assetId), $variant);
    }

    public function findReady(int $assetId): ?array
    {
        if ($assetId < 1 || !$this->db->tableExists('media_assets')) {
            return null;
        }
        $asset = $this->assets->find($assetId);
        return $asset && ($asset['status'] ?? '') === 'ready' ? $asset : null;
    }

    /**
     * Registers an existing public HTTP(S) media reference. No request is made
     * to the remote host and no file bytes are copied into application storage.
     */
    public function registerExternalUrl(array $auth, array $input): array
    {
        $this->assertExternalSchema();
        $user = $this->access->user($auth);
        $sourceUrl = ExternalMediaUrl::canonicalize((string) ($input['sourceUrl'] ?? $input['source_url'] ?? ''));
        if ($sourceUrl === null) {
            throw new MediaException('validation_failed', 'External media URL is invalid.', 422, ['sourceUrl' => 'invalid']);
        }
        $mediaType = strtolower(trim((string) ($input['mediaType'] ?? $input['media_type'] ?? 'image')));
        if (!in_array($mediaType, ['image', 'audio', 'video'], true)) {
            throw new MediaException('validation_failed', 'External media type is invalid.', 422, ['mediaType' => 'invalid']);
        }
        $category = $this->category((string) ($input['category'] ?? 'maps'));
        $fingerprint = ExternalMediaUrl::fingerprint($sourceUrl);
        $existing = $this->assets->withDeleted()->where('provider', 'external')
            ->where('provider_asset_id', $fingerprint)->first();
        if ($existing) {
            if (!empty($existing['deleted_at'])) {
                $this->db->table('media_assets')->where('id', (int) $existing['id'])->update([
                    'deleted_at' => null, 'status' => 'ready', 'source_url' => $sourceUrl,
                    'availability_status' => 'unknown', 'availability_checked_at' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $existing = $this->assets->find((int) $existing['id']);
            }
            return $this->presentTrusted($existing);
        }

        $filename = ExternalMediaUrl::filename($sourceUrl);
        $mimeType = $mediaType . '/*';
        $asset = [
            // An external reference is a public catalogue entry. Access to its use
            // remains guarded by the scene endpoint; no private remote URL is exposed.
            'owner_user_id' => (int) ($input['ownerUserId'] ?? $input['owner_user_id'] ?? $user['id']) ?: $user['id'],
            'campaign_id' => null,
            'provider' => 'external',
            'provider_container' => 'external',
            'provider_asset_id' => $fingerprint,
            'public_id' => null,
            'source_url' => $sourceUrl,
            'resource_type' => $mediaType,
            'category' => $category,
            'name' => $this->displayName((string) ($input['name'] ?? ''), $filename),
            'description' => isset($input['description']) ? trim((string) $input['description']) : null,
            'tags' => is_array($input['tags'] ?? null) ? array_values($input['tags']) : [
                'external', $category === 'audio' ? 'audio' : 'scene',
            ],
            'original_filename' => $filename,
            'mime_type' => $mimeType,
            'format' => $this->format($filename, $mimeType),
            'file_size' => null,
            'visibility' => 'public',
            'status' => 'ready',
            'availability_status' => 'unknown',
            'availability_checked_at' => null,
            'revision' => 1,
            'metadata' => [
                'source' => 'external_url',
                'availability_policy' => 'not_checked_server_side',
            ],
            'custom_metadata' => is_array($input['customMetadata'] ?? null) ? $input['customMetadata'] : [],
            'upload_expires_at' => null,
        ];
        if (!$this->assets->insert($asset)) {
            $existing = $this->assets->where('provider', 'external')
                ->where('provider_asset_id', $fingerprint)->first();
            if ($existing) return $this->presentTrusted($existing);
            throw new MediaException('media_write_failed', 'Could not register the external media source.', 500, $this->assets->errors());
        }
        $asset['id'] = (int) $this->assets->getInsertID();
        return $this->presentTrusted($asset);
    }

    public function presentTrusted(array $asset, ?string $variant = null): array
    {
        $ready = ($asset['status'] ?? '') === 'ready';
        $result = [
            'id' => (int) ($asset['id'] ?? 0),
            'resourceType' => (string) ($asset['resource_type'] ?? ''),
            'category' => (string) ($asset['category'] ?? ''),
            'name' => (string) ($asset['name'] ?? $asset['original_filename'] ?? ''),
            'filename' => $asset['original_filename'] ?? null,
            'mimeType' => (string) ($asset['mime_type'] ?? ''),
            'fileSize' => isset($asset['file_size']) ? (int) $asset['file_size'] : null,
            'width' => isset($asset['width']) ? (int) $asset['width'] : null,
            'height' => isset($asset['height']) ? (int) $asset['height'] : null,
            'duration' => isset($asset['duration']) ? (float) $asset['duration'] : null,
            'visibility' => (string) ($asset['visibility'] ?? ''),
            'status' => (string) ($asset['status'] ?? ''),
            'revision' => (int) ($asset['revision'] ?? 1),
            'provider' => (string) ($asset['provider'] ?? ''),
            'sourceUrl' => ($asset['provider'] ?? '') === 'external' ? ($asset['source_url'] ?? null) : null,
            'availabilityStatus' => ($asset['provider'] ?? '') === 'external'
                ? (string) ($asset['availability_status'] ?? 'unknown') : null,
            'availabilityCheckedAt' => ($asset['provider'] ?? '') === 'external'
                ? ($asset['availability_checked_at'] ?? null) : null,
            'url' => $ready ? $this->provider((string) $asset['provider'])->deliveryUrl($asset, $variant) : null,
        ];
        if ($ready && ($asset['resource_type'] ?? '') === 'image') {
            $result['variants'] = [];
            foreach (array_keys(CloudinaryMediaProvider::VARIANTS) as $name) {
                if (($asset['provider'] ?? '') !== 'cloudinary') {
                    break;
                }
                $result['variants'][$name] = $this->provider('cloudinary')->deliveryUrl($asset, $name);
            }
        }
        return $result;
    }

    public function importCloudinaryAsset(
        string $publicId,
        string $category,
        string $visibility = 'public',
        ?int $ownerUserId = null,
        ?int $campaignId = null
    ): array {
        $this->assertSchema();
        $category = $this->category($category);
        $provider = $this->provider('cloudinary');
        if (!$provider instanceof CloudinaryMediaProvider) {
            throw new MediaException('media_provider_unavailable', 'Cloudinary provider is not available.', 503);
        }
        $publicId = $provider->normalizePublicId($publicId);
        if ($publicId === '') {
            throw new MediaException('validation_failed', 'Cloudinary public ID cannot be empty.', 422);
        }
        $providerContainer = $provider->storageContainer([]);
        $existing = $this->assets->where('provider', 'cloudinary')
            ->where('provider_container', $providerContainer)
            ->where('public_id', $publicId)->first();
        if ($existing) {
            return $existing;
        }
        $row = [
            'owner_user_id' => $ownerUserId,
            'campaign_id' => $visibility === 'campaign' ? $campaignId : null,
            'provider' => 'cloudinary',
            'provider_container' => $providerContainer,
            'provider_asset_id' => null,
            'public_id' => $publicId,
            'resource_type' => 'image',
            'category' => $category,
            'name' => basename($publicId),
            'description' => null,
            'tags' => [],
            'original_filename' => null,
            'mime_type' => 'image/*',
            'format' => null,
            'file_size' => null,
            'visibility' => $visibility,
            'status' => 'ready',
            'revision' => 1,
            'metadata' => ['delivery_type' => 'upload', 'imported' => true],
            'custom_metadata' => [],
        ];
        if (!$this->assets->insert($row)) {
            $existing = $this->assets->where('provider', 'cloudinary')
                ->where('provider_container', $providerContainer)
                ->where('public_id', $publicId)->first();
            if ($existing) {
                return $existing;
            }
            throw new MediaException('media_write_failed', 'Could not import the Cloudinary asset.', 500, $this->assets->errors());
        }
        $row['id'] = (int) $this->assets->getInsertID();
        return $row;
    }

    private function find(int $assetId): array
    {
        $asset = $assetId > 0 ? $this->assets->find($assetId) : null;
        if (!$asset || ($asset['status'] ?? '') === 'deleted') {
            throw new MediaException('media_not_found', 'Media asset was not found.', 404);
        }
        return $asset;
    }

    private function findIncludingFailedDelete(int $assetId): array
    {
        $asset = $assetId > 0 ? $this->assets->find($assetId) : null;
        if (!$asset || !in_array(
            (string) ($asset['status'] ?? ''),
            ['pending', 'ready', 'failed', 'delete_failed'],
            true
        )) {
            throw new MediaException('media_not_found', 'Media asset was not found.', 404);
        }
        return $asset;
    }

    private function provider(string $name): MediaProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw new MediaException('media_provider_unavailable', 'Media provider is not available.', 503);
        }
        return $this->providers[$name];
    }

    private function storageIdentifier(string $provider, string $category, string $filename, int $userId): string
    {
        $uuid = $this->uuid();
        if ($provider === 'cloudinary') {
            return sprintf('media/%s/%s/%s', $category, gmdate('Y/m'), $uuid);
        }
        return sprintf('media/%s/%d/%s/%s', gmdate('Y/m'), $userId, $uuid, $filename);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function filename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', trim($filename)));
        $filename = (string) preg_replace('/[^a-zA-Z0-9._-]+/', '-', $filename);
        $filename = trim($filename, '.-');
        return substr($filename !== '' ? $filename : 'upload', 0, 255);
    }

    private function resourceType(string $mimeType): string
    {
        if (strpos($mimeType, 'image/') === 0) {
            return 'image';
        }
        if (strpos($mimeType, 'audio/') === 0) {
            return 'audio';
        }
        if (strpos($mimeType, 'video/') === 0) {
            return 'video';
        }
        if ($mimeType === 'application/pdf') {
            return 'document';
        }
        return 'raw';
    }

    private function category(string $category): string
    {
        $category = strtolower(trim($category));
        if (in_array($category, ['avatar', 'portrait', 'token', 'fullbody'], true)) {
            $category = 'characters';
        }
        if (!in_array($category, MediaAssetModel::CATEGORIES, true)) {
            throw new MediaException('validation_failed', 'Media category is invalid.', 422);
        }
        return $category;
    }

    private function displayName(string $name, string $filename): string
    {
        $name = trim($name);
        return mb_substr($name !== '' ? $name : pathinfo($filename, PATHINFO_FILENAME), 0, 255);
    }

    private function format(string $filename, string $mimeType): ?string
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = strtolower((string) substr(strrchr($mimeType, '/') ?: '', 1));
        }
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        return preg_match('/^[a-z0-9]{1,32}$/', $extension) ? $extension : null;
    }

    private function positiveId($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new MediaException('validation_failed', 'Campaign ID is invalid.', 422);
        }
        return (int) $value;
    }

    private function maxUploadBytes(): int
    {
        $configured = filter_var(getenv('MEDIA_UPLOAD_MAX_BYTES'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        return $configured === false ? self::DEFAULT_MAX_BYTES : (int) $configured;
    }

    private function assertSchema(): void
    {
        if (!$this->db->tableExists('media_assets')
            || !$this->db->fieldExists('provider_container', 'media_assets')) {
            throw new MediaException('media_schema_unavailable', 'Media storage schema is not available.', 503);
        }
    }

    private function assertExternalSchema(): void
    {
        $this->assertSchema();
        foreach (['source_url', 'availability_status', 'availability_checked_at'] as $field) {
            if (!$this->db->fieldExists($field, 'media_assets')) {
                throw new MediaException('media_schema_unavailable', 'External media schema is not available.', 503);
            }
        }
    }
}
