<?php

namespace App\Services\Audio;

use App\Services\Campaign\CampaignException;
use CodeIgniter\HTTP\Files\UploadedFile;

/** Stores campaign audio outside the public document root. */
final class AudioTrackStorage
{
    private const DEFAULT_LIMIT = 52428800;

    private $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?: WRITEPATH . 'uploads/audio', DIRECTORY_SEPARATOR);
    }

    public function store(int $campaignId, ?UploadedFile $file): array
    {
        return $this->storeIn('campaigns/' . $campaignId, $file);
    }

    public function storeForLibrary(int $libraryId, ?UploadedFile $file): array
    {
        return $this->storeIn('libraries/' . $libraryId, $file);
    }

    private function storeIn(string $namespace, ?UploadedFile $file): array
    {
        if (!$file || !$file->isValid() || $file->hasMoved()) {
            throw new CampaignException('invalid_audio_file', 'Choose a valid audio file.', 422);
        }
        $mime = strtolower(trim((string) $file->getMimeType()));
        $extension = strtolower(trim((string) $file->getClientExtension()));
        $allowed = [
            'audio/mpeg' => ['mp3'],
            'audio/mp3' => ['mp3'],
            'audio/wav' => ['wav'],
            'audio/x-wav' => ['wav'],
            'audio/wave' => ['wav'],
            'audio/ogg' => ['ogg', 'oga'],
            'application/ogg' => ['ogg', 'oga'],
            'audio/webm' => ['webm'],
            'audio/mp4' => ['m4a', 'mp4'],
            'audio/x-m4a' => ['m4a'],
            'audio/aac' => ['aac'],
            'audio/flac' => ['flac'],
            'audio/x-flac' => ['flac'],
        ];
        $size = (int) $file->getSize();
        if (!isset($allowed[$mime]) || !in_array($extension, $allowed[$mime], true)
            || $size < 1 || $size > $this->limitBytes()) {
            throw new CampaignException(
                'invalid_audio_file',
                'Audio type, extension, or size is not allowed.',
                422
            );
        }

        $random = bin2hex(random_bytes(20));
        $relative = trim($namespace, '/') . '/' . substr($random, 0, 2) . '/' . $random . '.' . $extension;
        $directory = $this->basePath . DIRECTORY_SEPARATOR . dirname($relative);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new CampaignException('audio_storage_failed', 'Private audio storage is unavailable.', 500);
        }
        $file->move($directory, basename($relative));
        $path = $directory . DIRECTORY_SEPARATOR . basename($relative);
        if (!$file->hasMoved() || !is_file($path)) {
            throw new CampaignException('audio_storage_failed', 'Audio file could not be stored.', 500);
        }
        return [
            'storage_key' => str_replace(DIRECTORY_SEPARATOR, '/', $relative),
            'original_name' => $this->safeName((string) $file->getClientName()),
            'mime_type' => $mime,
            'extension' => $extension,
            'byte_size' => filesize($path),
            'sha256' => hash_file('sha256', $path),
            'path' => $path,
        ];
    }

    public function path(string $storageKey): string
    {
        $key = str_replace('\\', '/', trim($storageKey));
        if ($key === '' || str_starts_with($key, '/') || str_contains($key, '../')) {
            throw new CampaignException('audio_track_not_found', 'Audio track was not found.', 404);
        }
        return $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
    }

    public function remove(string $storageKey): void
    {
        $path = $this->path($storageKey);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function limitBytes(): int
    {
        $configured = getenv('AUDIO_UPLOAD_MAX_BYTES');
        return ctype_digit((string) $configured) && (int) $configured > 0
            ? (int) $configured : self::DEFAULT_LIMIT;
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', trim($name)));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        return mb_substr((string) ($name ?: 'audio'), 0, 255);
    }
}
