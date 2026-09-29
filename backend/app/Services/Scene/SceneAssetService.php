<?php

namespace App\Services\Scene;

use App\Services\Campaign\CampaignAccessService;
use App\Services\Media\MediaException;
use App\Services\Media\MediaService;
use CodeIgniter\HTTP\Files\UploadedFile;

/** Campaign-scoped map backgrounds stored outside the public document root. */
final class SceneAssetService
{
    private const FILE_LIMIT = 26214400;
    private const CAMPAIGN_QUOTA = 1073741824;

    private $access;
    private $basePath;
    private $db;
    private $media;

    public function __construct(?CampaignAccessService $access = null, ?string $basePath = null, ?MediaService $media = null)
    {
        $this->access = $access ?: new CampaignAccessService();
        $this->basePath = rtrim($basePath ?: WRITEPATH . 'uploads/scenes', DIRECTORY_SEPARATOR);
        $this->db = \Config\Database::connect();
        $this->media = $media ?: new MediaService($this->db);
    }

    public function list(int $campaignId, array $auth): array
    {
        $this->authorize($campaignId, $auth, true);
        $items = [];
        $linkedKeys = [];
        if ($this->db->tableExists('scene_media_assets')) {
            $rows = $this->db->table('scene_media_assets link')
                ->select('link.legacy_key, media.name, media.mime_type, media.file_size, media.width, media.height, media.created_at')
                ->join('media_assets media', 'media.id = link.media_asset_id', 'inner')
                ->where('link.campaign_id', $campaignId)->where('media.deleted_at', null)
                ->where('media.status', 'ready')->get()->getResultArray();
            foreach ($rows as $row) {
                $linkedKeys[(string) $row['legacy_key']] = true;
                $items[] = $this->present($campaignId, [
                    'key' => $row['legacy_key'], 'name' => $row['name'], 'mimeType' => $row['mime_type'],
                    'byteSize' => (int) $row['file_size'], 'width' => (int) $row['width'],
                    'height' => (int) $row['height'], 'createdAt' => $row['created_at'],
                ]);
            }
        }
        $directory = $this->campaignDirectory($campaignId);
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $metadataPath) {
            $metadata = json_decode((string) @file_get_contents($metadataPath), true);
            if (!is_array($metadata) || !$this->validKey((string) ($metadata['key'] ?? ''))) {
                continue;
            }
            if (isset($linkedKeys[(string) $metadata['key']])) continue;
            $imagePath = $directory . DIRECTORY_SEPARATOR . $metadata['key'];
            if (is_file($imagePath)) {
                $items[] = $this->present($campaignId, $metadata);
            }
        }
        usort($items, static fn (array $left, array $right): int =>
            strcmp((string) ($right['createdAt'] ?? ''), (string) ($left['createdAt'] ?? ''))
        );
        return ['items' => $items];
    }

    public function upload(int $campaignId, array $auth, ?UploadedFile $file): array
    {
        $this->authorize($campaignId, $auth, true);
        if (!$file || !$file->isValid() || $file->hasMoved()) {
            throw new SceneException('invalid_scene_asset', 'Choose a valid map image.', 422);
        }
        $mime = strtolower((string) $file->getMimeType());
        if ($mime === 'image/x-webp') {
            $mime = 'image/webp';
        }
        $types = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $size = (int) $file->getSize();
        $dimensions = @getimagesize($file->getTempName());
        if (!isset($types[$mime]) || $size < 1 || $size > self::FILE_LIMIT || !$dimensions) {
            throw new SceneException(
                'invalid_scene_asset',
                'Use a valid PNG, JPEG, WebP or GIF image up to 25 MB.',
                422
            );
        }
        $directory = $this->campaignDirectory($campaignId, true);
        if ($this->directorySize($directory) + $size > self::CAMPAIGN_QUOTA) {
            throw new SceneException('scene_asset_quota_exceeded', 'The campaign map library is full.', 422);
        }
        $key = bin2hex(random_bytes(16)) . '.' . $types[$mime];
        $clientName = $this->safeName((string) $file->getClientName());
        $file->move($directory, $key);
        if (!$file->hasMoved()) {
            throw new SceneException('scene_asset_storage_failed', 'The map image could not be stored.', 500);
        }
        $metadata = [
            'key' => $key,
            'name' => $clientName,
            'mimeType' => $mime,
            'byteSize' => $size,
            'width' => (int) $dimensions[0],
            'height' => (int) $dimensions[1],
            'createdAt' => gmdate('c'),
        ];
        $metadataPath = $directory . DIRECTORY_SEPARATOR . $key . '.json';
        if (@file_put_contents(
            $metadataPath,
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        ) === false) {
            @unlink($directory . DIRECTORY_SEPARATOR . $key);
            throw new SceneException('scene_asset_storage_failed', 'Map metadata could not be stored.', 500);
        }
        if (!$this->db->tableExists('media_assets') || !$this->db->tableExists('scene_media_assets')) {
            return ['asset' => $this->present($campaignId, $metadata)];
        }
        try {
            $central = $this->media->uploadFile($auth, $directory . DIRECTORY_SEPARATOR . $key, [
                'filename' => $clientName, 'name' => pathinfo($clientName, PATHINFO_FILENAME),
                'mimeType' => $mime, 'category' => 'maps', 'visibility' => 'campaign',
                'campaignId' => $campaignId, 'ownerUserId' => (int) ($auth['user_id'] ?? 0),
            ]);
            $now = date('Y-m-d H:i:s');
            $written = $this->db->table('scene_media_assets')->insert([
                'campaign_id' => $campaignId, 'legacy_key' => $key,
                'media_asset_id' => (int) $central['id'], 'created_at' => $now, 'updated_at' => $now,
            ]);
            if (!$written) {
                try {
                    $this->media->purge((int) $central['id']);
                } catch (\Throwable $ignored) {
                    // A failed cleanup remains visible to administrators as delete_failed.
                }
                throw new SceneException('scene_asset_storage_failed', 'Map metadata could not be saved.', 500);
            }
            @unlink($directory . DIRECTORY_SEPARATOR . $key);
            @unlink($metadataPath);
        } catch (MediaException $exception) {
            @unlink($directory . DIRECTORY_SEPARATOR . $key);
            @unlink($metadataPath);
            throw new SceneException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->errors());
        } catch (\Throwable $exception) {
            @unlink($directory . DIRECTORY_SEPARATOR . $key);
            @unlink($metadataPath);
            if ($exception instanceof SceneException) throw $exception;
            throw new SceneException('scene_asset_storage_failed', 'Map metadata could not be saved.', 500);
        }
        return ['asset' => $this->present($campaignId, $metadata)];
    }

    public function download(int $campaignId, string $key, array $auth): array
    {
        $this->authorize($campaignId, $auth, false);
        if (!$this->validKey($key)) {
            throw new SceneException('scene_asset_not_found', 'Map image was not found.', 404);
        }
        if ($this->db->tableExists('scene_media_assets')) {
            $link = $this->db->table('scene_media_assets')->where('campaign_id', $campaignId)
                ->where('legacy_key', $key)->get()->getRowArray();
            if ($link) {
                try {
                    $media = $this->media->getTrusted((int) $link['media_asset_id']);
                } catch (MediaException $exception) {
                    throw new SceneException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->errors());
                }
                return ['url' => $media['url'], 'asset' => $media];
            }
        }
        $directory = $this->campaignDirectory($campaignId);
        $path = $directory . DIRECTORY_SEPARATOR . $key;
        $metadata = json_decode((string) @file_get_contents($path . '.json'), true);
        if (!is_file($path) || !is_array($metadata) || ($metadata['key'] ?? null) !== $key) {
            throw new SceneException('scene_asset_not_found', 'Map image was not found.', 404);
        }
        return ['path' => $path, 'asset' => $this->present($campaignId, $metadata)];
    }

    private function authorize(int $campaignId, array $auth, bool $manage): void
    {
        $access = $this->access->forCampaign($auth, $campaignId);
        if (!$access['exists']) {
            throw new SceneException('campaign_not_found', 'Campaign was not found.', 404);
        }
        if (!$access['allowed'] || ($manage && empty($access['capabilities']['canManage']))) {
            throw new SceneException('forbidden', 'You cannot access this map library.', 403);
        }
    }

    private function campaignDirectory(int $campaignId, bool $create = false): string
    {
        if ($campaignId < 1) {
            throw new SceneException('campaign_not_found', 'Campaign was not found.', 404);
        }
        $directory = $this->basePath . DIRECTORY_SEPARATOR . $campaignId;
        if ($create && !is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new SceneException('scene_asset_storage_failed', 'Map storage is unavailable.', 500);
        }
        return $directory;
    }

    private function directorySize(string $directory): int
    {
        $size = 0;
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path) && substr($path, -5) !== '.json') {
                $size += (int) filesize($path);
            }
        }
        return $size;
    }

    private function validKey(string $key): bool
    {
        return preg_match('/^[a-f0-9]{32}\.(?:png|jpg|webp|gif)$/', $key) === 1;
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', trim($name)));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = mb_substr((string) $name, 0, 255);
        return $name !== '' ? $name : 'scene-background';
    }

    private function present(int $campaignId, array $metadata): array
    {
        $key = (string) $metadata['key'];
        return $metadata + [
            'url' => '/api/campaigns/' . $campaignId . '/scene-assets/' . rawurlencode($key) . '/file',
        ];
    }
}
