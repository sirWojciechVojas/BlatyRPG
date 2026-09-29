<?php

namespace App\Services\Token;

use CodeIgniter\HTTP\Files\UploadedFile;

final class TokenTemplateAssetStorage
{
    private const FILE_LIMIT = 26214400;
    private $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim(
            $basePath ?: WRITEPATH . 'uploads/token-templates',
            DIRECTORY_SEPARATOR
        );
    }

    public function store(?UploadedFile $file): array
    {
        if (!$file || !$file->isValid() || $file->hasMoved()) {
            throw new TokenException('invalid_token_template_asset', 'Choose a valid token image.', 422);
        }
        $mime = strtolower((string) $file->getMimeType());
        if ($mime === 'image/x-webp') $mime = 'image/webp';
        $types = [
            'image/png' => 'png', 'image/jpeg' => 'jpg',
            'image/webp' => 'webp', 'image/gif' => 'gif',
        ];
        $size = (int) $file->getSize();
        $dimensions = @getimagesize($file->getTempName());
        if (!isset($types[$mime]) || $size < 1 || $size > self::FILE_LIMIT || !$dimensions) {
            throw new TokenException(
                'invalid_token_template_asset',
                'Use a valid PNG, JPEG, WebP or GIF image up to 25 MB.',
                422
            );
        }
        $this->ensureDirectory();
        $key = bin2hex(random_bytes(16)) . '.' . $types[$mime];
        $file->move($this->basePath, $key);
        if (!$file->hasMoved()) {
            throw new TokenException('token_template_asset_storage_failed', 'The token image could not be stored.', 500);
        }
        return [
            'storage_key' => $key,
            'original_name' => $this->safeName((string) $file->getClientName()),
            'mime_type' => $mime,
            'byte_size' => $size,
            'width' => (int) $dimensions[0],
            'height' => (int) $dimensions[1],
        ];
    }

    public function path(string $key): string
    {
        if (preg_match('/^[a-f0-9]{32}\.(?:png|jpg|webp|gif)$/', $key) !== 1) {
            throw new TokenException('token_template_asset_not_found', 'Token image was not found.', 404);
        }
        $path = $this->basePath . DIRECTORY_SEPARATOR . $key;
        if (!is_file($path)) {
            throw new TokenException('token_template_asset_not_found', 'Token image was not found.', 404);
        }
        return $path;
    }

    public function discard(string $key): void
    {
        if (preg_match('/^[a-f0-9]{32}\.(?:png|jpg|webp|gif)$/', $key) === 1) {
            @unlink($this->basePath . DIRECTORY_SEPARATOR . $key);
        }
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->basePath)
            && !mkdir($this->basePath, 0770, true)
            && !is_dir($this->basePath)) {
            throw new TokenException('token_template_asset_storage_failed', 'Token image storage is unavailable.', 500);
        }
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', trim($name)));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = mb_substr((string) $name, 0, 255);
        return $name !== '' ? $name : 'token-image';
    }
}
