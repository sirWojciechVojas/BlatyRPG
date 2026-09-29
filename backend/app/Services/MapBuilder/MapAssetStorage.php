<?php

namespace App\Services\MapBuilder;

use App\Services\Media\MediaException;
use App\Services\Media\MediaService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use ZipArchive;

/** Campaign-private, metadata-backed image storage for imported and AI-created map assets. */
final class MapAssetStorage
{
    private const FILE_LIMIT = 26214400;
    private const PACKAGE_LIMIT = 104857600;
    private const CAMPAIGN_QUOTA = 2147483648;
    private const PACKAGE_ASSET_LIMIT = 500;

    private $db;
    private $basePath;
    private $media;

    public function __construct(?BaseConnection $db = null, ?string $basePath = null, ?MediaService $media = null)
    {
        $this->db = $db ?: \Config\Database::connect();
        $this->basePath = rtrim($basePath ?: WRITEPATH . 'uploads/map-builder', DIRECTORY_SEPARATOR);
        $this->media = $media ?: new MediaService($this->db);
    }

    public function list(int $campaignId): array
    {
        $rows = $this->db->table('map_assets')->where('campaign_id', $campaignId)
            ->where('deleted_at', null)->orderBy('created_at', 'DESC')->get()->getResultArray();
        return ['items' => array_map(fn (array $row): array => $this->present($row), $rows)];
    }

    public function upload(int $campaignId, int $userId, ?UploadedFile $file, array $metadata): array
    {
        if (!$file || !$file->isValid() || $file->hasMoved()) {
            throw new MapBuilderException('map_asset_invalid', 'Choose a valid map asset image.', 422);
        }
        $bytes = (string) @file_get_contents($file->getTempName());
        return ['asset' => $this->storeBytes(
            $campaignId,
            $userId,
            $bytes,
            (string) $file->getClientName(),
            $metadata,
            'upload'
        )];
    }

    public function storeGenerated(
        int $campaignId,
        int $userId,
        string $bytes,
        string $name,
        array $metadata,
        string $origin = 'ai'
    ): array {
        return $this->storeBytes($campaignId, $userId, $bytes, $name, $metadata, $origin);
    }

    public function importPackage(int $campaignId, int $userId, ?UploadedFile $file): array
    {
        if (!$file || !$file->isValid() || $file->hasMoved()
            || $file->getSize() < 1 || $file->getSize() > self::PACKAGE_LIMIT) {
            throw new MapBuilderException('map_asset_package_invalid', 'Choose a ZIP package up to 100 MB.', 422);
        }
        $zip = new ZipArchive();
        if ($zip->open($file->getTempName()) !== true) {
            throw new MapBuilderException('map_asset_package_invalid', 'Asset package is not a valid ZIP file.', 422);
        }
        try {
            $manifestRaw = $zip->getFromName('manifest.json');
            $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
            $items = is_array($manifest) ? ($manifest['assets'] ?? null) : null;
            if (!is_array($items) || !$items || count($items) > self::PACKAGE_ASSET_LIMIT) {
                throw new MapBuilderException(
                    'map_asset_package_invalid',
                    'manifest.json must contain between 1 and 500 assets.',
                    422
                );
            }
            $stored = [];
            $uncompressedBytes = 0;
            $this->db->transBegin();
            try {
                foreach ($items as $index => $item) {
                    if (!is_array($item)) {
                        throw new MapBuilderException('map_asset_package_invalid', 'Asset metadata is invalid.', 422, [
                            "assets.{$index}" => 'Object required.',
                        ]);
                    }
                    $path = (string) ($item['file'] ?? '');
                    if (!$this->safePackagePath($path)) {
                        throw new MapBuilderException('map_asset_package_invalid', 'Asset package contains an unsafe path.', 422);
                    }
                    $stat = $zip->statName($path);
                    $entrySize = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;
                    $compressedSize = is_array($stat) ? (int) ($stat['comp_size'] ?? 0) : 0;
                    $uncompressedBytes += $entrySize;
                    if ($entrySize < 1 || $entrySize > self::FILE_LIMIT
                        || $uncompressedBytes > self::CAMPAIGN_QUOTA
                        || ($compressedSize > 0 && $entrySize / $compressedSize > 200)) {
                        throw new MapBuilderException(
                            'map_asset_package_invalid',
                            'Asset package contains an oversized or unsafe compressed entry.',
                            422
                        );
                    }
                    $bytes = $zip->getFromName($path);
                    if (!is_string($bytes)) {
                        throw new MapBuilderException('map_asset_package_invalid', 'Asset file is missing from package.', 422, [
                            "assets.{$index}.file" => $path,
                        ]);
                    }
                    $stored[] = $this->storeBytes(
                        $campaignId,
                        $userId,
                        $bytes,
                        basename($path),
                        $item,
                        'package'
                    );
                }
                if ($this->db->transStatus() === false) {
                    throw new MapBuilderException('map_asset_storage_failed', 'Asset package could not be saved.', 500);
                }
                $this->db->transCommit();
            } catch (\Throwable $exception) {
                $this->db->transRollback();
                foreach ($stored as $asset) $this->deletePhysical((string) ($asset['storageKey'] ?? ''));
                throw $exception;
            }
            return ['items' => $stored, 'importedCount' => count($stored)];
        } finally {
            $zip->close();
        }
    }

    public function download(int $campaignId, int $assetId): array
    {
        $row = $this->db->table('map_assets')->where('campaign_id', $campaignId)
            ->where('id', $assetId)->where('deleted_at', null)->get()->getRowArray();
        if (!$row) throw new MapBuilderException('map_asset_not_found', 'Map asset was not found.', 404);
        if (!empty($row['media_asset_id'])) {
            try {
                $media = $this->media->getTrusted((int) $row['media_asset_id']);
            } catch (MediaException $exception) {
                throw new MapBuilderException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->errors());
            }
            return ['url' => $media['url'], 'asset' => $this->present($row)];
        }
        $path = $this->campaignDirectory($campaignId) . DIRECTORY_SEPARATOR . $row['storage_key'];
        if (!is_file($path)) throw new MapBuilderException('map_asset_not_found', 'Map asset file is missing.', 404);
        return ['path' => $path, 'asset' => $this->present($row)];
    }

    public function exists(int $campaignId, string $stableId): bool
    {
        return $this->db->table('map_assets')->where('campaign_id', $campaignId)
            ->where('stable_id', $stableId)->where('deleted_at', null)->countAllResults() > 0;
    }

    private function storeBytes(
        int $campaignId,
        int $userId,
        string $bytes,
        string $originalName,
        array $metadata,
        string $origin
    ): array {
        $size = strlen($bytes);
        $dimensions = $size > 0 ? @getimagesizefromstring($bytes) : false;
        $mime = is_array($dimensions) ? strtolower((string) ($dimensions['mime'] ?? '')) : '';
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if ($size < 1 || $size > self::FILE_LIMIT || !$dimensions || !isset($extensions[$mime])) {
            throw new MapBuilderException(
                'map_asset_invalid',
                'Use a valid PNG, JPEG or WebP image up to 25 MB.',
                422
            );
        }
        $directory = $this->campaignDirectory($campaignId, true);
        if ($this->directorySize($directory) + $size > self::CAMPAIGN_QUOTA) {
            throw new MapBuilderException('map_asset_quota_exceeded', 'Campaign map asset quota is full.', 422);
        }
        $stableId = $this->stableId((string) ($metadata['id'] ?? ''));
        $version = max(1, min(1000000, (int) ($metadata['version'] ?? 1)));
        if ($this->db->table('map_assets')->where('campaign_id', $campaignId)
            ->where('stable_id', $stableId)->where('version', $version)->countAllResults() > 0) {
            throw new MapBuilderException('map_asset_version_exists', 'This asset version already exists.', 409);
        }
        $storageKey = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
        $path = $directory . DIRECTORY_SEPARATOR . $storageKey;
        if (@file_put_contents($path, $bytes, LOCK_EX) !== $size) {
            @unlink($path);
            throw new MapBuilderException('map_asset_storage_failed', 'Map asset could not be stored.', 500);
        }
        $name = trim((string) ($metadata['name'] ?? pathinfo($originalName, PATHINFO_FILENAME)));
        $category = trim((string) ($metadata['category'] ?? 'własne'));
        $tags = array_values(array_unique(array_slice(array_filter(array_map(
            static fn ($tag): string => mb_substr(trim((string) $tag), 0, 50),
            is_array($metadata['tags'] ?? null) ? $metadata['tags'] : []
        )), 0, 50)));
        $safeMetadata = $this->metadata($metadata, $dimensions);
        $central = null;
        if ($this->db->tableExists('media_assets')
            && $this->db->fieldExists('media_asset_id', 'map_assets')) {
            try {
                $central = $this->media->uploadFile(
                    ['user_id' => $userId, 'anonymous' => false],
                    $path,
                    [
                        'filename' => $originalName, 'name' => $name,
                        'mimeType' => $mime, 'category' => 'map-creator',
                        'visibility' => 'campaign', 'campaignId' => $campaignId,
                        'ownerUserId' => $userId, 'tags' => $tags,
                        'customMetadata' => $safeMetadata,
                    ]
                );
            } catch (MediaException $exception) {
                @unlink($path);
                throw new MapBuilderException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->errors());
            }
        }
        $now = date('Y-m-d H:i:s');
        $rowData = [
            'campaign_id' => $campaignId,
            'stable_id' => $stableId,
            'version' => $version,
            'name' => mb_substr($name !== '' ? $name : 'Asset mapy', 0, 150),
            'category' => mb_substr($category !== '' ? $category : 'własne', 0, 80),
            'tags_json' => json_encode($tags, JSON_UNESCAPED_UNICODE),
            'metadata_json' => json_encode($safeMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'storage_key' => $central ? null : $storageKey,
            'original_name' => mb_substr($this->safeName($originalName), 0, 255),
            'mime_type' => $mime,
            'byte_size' => $size,
            'width' => (int) $dimensions[0],
            'height' => (int) $dimensions[1],
            'origin' => in_array($origin, ['upload', 'package', 'ai'], true) ? $origin : 'upload',
            'created_by_user_id' => $userId ?: null,
            'created_at' => $now,
        ];
        if ($this->db->fieldExists('media_asset_id', 'map_assets')) {
            $rowData['media_asset_id'] = $central ? (int) $central['id'] : null;
        }
        $written = $this->db->table('map_assets')->insert($rowData);
        if (!$written) {
            @unlink($path);
            throw new MapBuilderException('map_asset_storage_failed', 'Map asset metadata could not be saved.', 500);
        }
        if ($central) @unlink($path);
        $row = $this->db->table('map_assets')->where('id', (int) $this->db->insertID())->get()->getRowArray();
        return $this->present($row);
    }

    private function metadata(array $source, array $dimensions): array
    {
        $physical = is_array($source['physicalSize'] ?? null) ? $source['physicalSize'] : [];
        $anchor = is_array($source['anchor'] ?? null) ? $source['anchor'] : [];
        return [
            'physicalSize' => [
                'width' => max(0.01, min(1000, (float) ($physical['width'] ?? 1))),
                'height' => max(0.01, min(1000, (float) ($physical['height'] ?? 1))),
                'unit' => 'm',
            ],
            'anchor' => [
                'x' => max(0, min(1, (float) ($anchor['x'] ?? 0.5))),
                'y' => max(0, min(1, (float) ($anchor['y'] ?? 0.5))),
            ],
            'obstacle' => is_array($source['obstacle'] ?? null) ? $source['obstacle'] : null,
            'light' => is_array($source['light'] ?? null) ? $source['light'] : null,
            'materialMaps' => is_array($source['materialMaps'] ?? null) ? $source['materialMaps'] : [],
            'pixelSize' => ['width' => (int) $dimensions[0], 'height' => (int) $dimensions[1]],
            'provenance' => is_array($source['provenance'] ?? null)
                ? $source['provenance']
                : ['type' => 'user-upload', 'license' => 'declared-by-uploader'],
        ];
    }

    private function present(array $row): array
    {
        $metadata = json_decode((string) ($row['metadata_json'] ?? ''), true) ?: [];
        $tags = json_decode((string) ($row['tags_json'] ?? ''), true) ?: [];
        $url = '/api/campaigns/' . (int) $row['campaign_id'] . '/maps/assets/' . (int) $row['id'] . '/file';
        return [
            'databaseId' => (int) $row['id'],
            'id' => (string) $row['stable_id'],
            'version' => (int) $row['version'],
            'name' => (string) $row['name'],
            'category' => (string) $row['category'],
            'tags' => $tags,
            'kind' => 'sprite',
            'source' => ['type' => 'image', 'url' => $url],
            'thumbnails' => ['small' => $url, 'medium' => $url],
            'qualityVariants' => ['standard' => $url],
            'physicalSize' => $metadata['physicalSize'] ?? ['width' => 1, 'height' => 1, 'unit' => 'm'],
            'anchor' => $metadata['anchor'] ?? ['x' => 0.5, 'y' => 0.5],
            'obstacle' => $metadata['obstacle'] ?? null,
            'light' => $metadata['light'] ?? null,
            'materialMaps' => $metadata['materialMaps'] ?? [],
            'provenance' => $metadata['provenance'] ?? ['type' => (string) $row['origin']],
            'mimeType' => (string) $row['mime_type'],
            'byteSize' => (int) $row['byte_size'],
            'width' => (int) $row['width'],
            'height' => (int) $row['height'],
            'storageKey' => (string) $row['storage_key'],
            'createdAt' => (string) $row['created_at'],
        ];
    }

    private function stableId(string $candidate): string
    {
        $candidate = strtolower(trim($candidate));
        if (preg_match('/^custom\.[a-z0-9._-]{1,120}$/', $candidate)) return $candidate;
        return 'custom.' . bin2hex(random_bytes(16));
    }

    private function campaignDirectory(int $campaignId, bool $create = false): string
    {
        if ($campaignId < 1) throw new MapBuilderException('campaign_not_found', 'Campaign was not found.', 404);
        $directory = $this->basePath . DIRECTORY_SEPARATOR . $campaignId;
        if ($create && !is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new MapBuilderException('map_asset_storage_failed', 'Map asset storage is unavailable.', 500);
        }
        return $directory;
    }

    private function directorySize(string $directory): int
    {
        $size = 0;
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path)) $size += (int) filesize($path);
        }
        return $size;
    }

    private function safePackagePath(string $path): bool
    {
        return $path !== '' && strlen($path) <= 255 && $path[0] !== '/'
            && strpos(str_replace('\\', '/', $path), '../') === false
            && preg_match('/\.(?:png|jpe?g|webp)$/i', $path) === 1;
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', trim($name)));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        return $name !== '' ? $name : 'map-asset';
    }

    private function deletePhysical(string $storageKey): void
    {
        if (!preg_match('/^[a-f0-9]{40}\.(?:png|jpg|webp)$/', $storageKey)) return;
        foreach (glob($this->basePath . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $storageKey) ?: [] as $path) {
            @unlink($path);
        }
    }
}
