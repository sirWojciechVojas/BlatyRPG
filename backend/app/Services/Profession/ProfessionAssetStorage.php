<?php

namespace App\Services\Profession;

use CodeIgniter\HTTP\Files\UploadedFile;

final class ProfessionAssetStorage
{
    private const FILE_LIMIT = 26214400;
    private $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim(
            $basePath ?: WRITEPATH . 'uploads/professions',
            DIRECTORY_SEPARATOR
        );
    }

    public function store(?UploadedFile $file): array
    {
        if (!$file || !$file->isValid() || $file->hasMoved()) {
            throw new \InvalidArgumentException('Choose a valid profession image.');
        }
        $mime = strtolower((string) $file->getMimeType());
        if ($mime === 'image/x-webp') $mime = 'image/webp';
        $types = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        $size = (int) $file->getSize();
        $dimensions = @getimagesize($file->getTempName());
        if (!isset($types[$mime]) || $size < 1
            || $size > self::FILE_LIMIT || !$dimensions) {
            throw new \InvalidArgumentException(
                'Use a valid PNG, JPEG or WebP image up to 25 MB.'
            );
        }
        $this->ensureDirectory();
        $key = bin2hex(random_bytes(16)) . '.' . $types[$mime];
        $file->move($this->basePath, $key);
        if (!$file->hasMoved()) {
            throw new \RuntimeException('The profession image could not be stored.');
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
        if (!$this->validKey($key)) {
            throw new \RuntimeException('Profession image was not found.');
        }
        $path = $this->basePath . DIRECTORY_SEPARATOR . $key;
        if (!is_file($path)) {
            throw new \RuntimeException('Profession image was not found.');
        }
        return $path;
    }

    public function discard(string $key): void
    {
        if ($this->validKey($key)) {
            @unlink($this->basePath . DIRECTORY_SEPARATOR . $key);
        }
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->basePath)
            && !mkdir($this->basePath, 0770, true)
            && !is_dir($this->basePath)) {
            throw new \RuntimeException('Profession image storage is unavailable.');
        }
    }

    private function validKey(string $key): bool
    {
        return preg_match('/^[a-f0-9]{32}\.(?:png|jpg|webp)$/', $key) === 1;
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', trim($name)));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = mb_substr((string) $name, 0, 255);
        return $name !== '' ? $name : 'profession-figure';
    }
}
