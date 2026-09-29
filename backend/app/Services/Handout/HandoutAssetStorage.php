<?php

namespace App\Services\Handout;

use App\Services\Campaign\CampaignException;
use CodeIgniter\HTTP\Files\UploadedFile;

/** Private disk storage. Files are deliberately never written to FCPATH. */
final class HandoutAssetStorage
{
    private const IMAGE_LIMIT = 10485760;
    private const PDF_LIMIT = 26214400;
    private const QUOTA_LIMIT = 524288000;

    private $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?: WRITEPATH . 'uploads/handouts', DIRECTORY_SEPARATOR);
    }

    public function quotaBytes(): int
    {
        $configured = getenv('HANDOUT_LIBRARY_QUOTA_BYTES');
        return ctype_digit((string) $configured) ? (int) $configured : self::QUOTA_LIMIT;
    }

    public function store(int $ownerId, ?UploadedFile $file): array
    {
        if (!$file || !$file->isValid() || $file->hasMoved()) {
            throw new CampaignException('invalid_handout_file', 'Choose a valid file.', 422);
        }
        $mime = strtolower((string) $file->getMimeType());
        // Some libmagic/browser combinations still report the historical
        // x-webp alias. Persist the canonical media type so WebP behaves like
        // every other image throughout the handout API and frontend.
        if ($mime === 'image/x-webp') {
            $mime = 'image/webp';
        }
        $allowed = [
            'image/png' => ['png', self::IMAGE_LIMIT],
            'image/jpeg' => ['jpg', self::IMAGE_LIMIT],
            'image/webp' => ['webp', self::IMAGE_LIMIT],
            'image/gif' => ['gif', self::IMAGE_LIMIT],
            'application/pdf' => ['pdf', self::PDF_LIMIT],
        ];
        if (!isset($allowed[$mime]) || $file->getSize() < 1 || $file->getSize() > $allowed[$mime][1]) {
            throw new CampaignException('invalid_handout_file', 'File type or size is not allowed.', 422);
        }
        if (strpos($mime, 'image/') === 0 && !@getimagesize($file->getTempName())) {
            throw new CampaignException('invalid_handout_file', 'Image data is invalid.', 422);
        }
        if ($mime === 'application/pdf' && @file_get_contents($file->getTempName(), false, null, 0, 5) !== '%PDF-') {
            throw new CampaignException('invalid_handout_file', 'PDF data is invalid.', 422);
        }
        $random = bin2hex(random_bytes(16));
        $relative = $ownerId . DIRECTORY_SEPARATOR . substr($random, 0, 2) . DIRECTORY_SEPARATOR . $random . '.' . $allowed[$mime][0];
        $directory = $this->basePath . DIRECTORY_SEPARATOR . dirname($relative);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new CampaignException('handout_storage_failed', 'Private storage is unavailable.', 500);
        }
        $file->move($directory, basename($relative));
        if (!$file->hasMoved()) {
            throw new CampaignException('handout_storage_failed', 'File could not be stored.', 500);
        }
        $path = $directory . DIRECTORY_SEPARATOR . basename($relative);
        return [
            'storageKey' => str_replace(DIRECTORY_SEPARATOR, '/', $relative),
            'mimeType' => $mime,
            'byteSize' => filesize($path),
            'sha256' => hash_file('sha256', $path),
            'path' => $path,
            'originalName' => $this->safeName((string) $file->getClientName()),
        ];
    }

    public function path(string $storageKey): string
    {
        $key = str_replace('\\', '/', trim($storageKey));
        if ($key === '' || strpos($key, '../') !== false || strpos($key, '/') === 0) {
            throw new CampaignException('handout_asset_not_found', 'Handout asset was not found.', 404);
        }
        return $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
    }

    public function remove(string $storageKey): void
    {
        $path = $this->path($storageKey);
        if (is_file($path)) @unlink($path);
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', trim($name)));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = mb_substr((string) $name, 0, 255);
        return $name !== '' ? $name : 'handout-asset';
    }
}
