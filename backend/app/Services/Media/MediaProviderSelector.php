<?php

namespace App\Services\Media;

final class MediaProviderSelector
{
    private const CLOUDINARY_CATEGORIES = [
        'image', 'avatar', 'token', 'portrait', 'texture', 'map-asset',
        'characters', 'npcs', 'monsters', 'items', 'textures', 'map-creator',
    ];
    private const R2_CATEGORIES = [
        'audio', 'video', 'documents', 'large-map', 'pdf', 'export', 'document', 'archive', 'binary',
    ];
    private const LARGE_IMAGE_BYTES = 25 * 1024 * 1024;

    public function select(string $category, string $mimeType, int $fileSize): string
    {
        $category = strtolower(trim($category));
        $mimeType = strtolower(trim($mimeType));

        if ($category === 'large-map'
            || (in_array($category, self::R2_CATEGORIES, true) && strpos($mimeType, 'image/') !== 0)) {
            return 'r2';
        }
        if (in_array($category, ['map', 'maps', 'map-creator'], true)
            && strpos($mimeType, 'image/') === 0
            && $fileSize > self::LARGE_IMAGE_BYTES) {
            return 'r2';
        }
        if (in_array($category, self::CLOUDINARY_CATEGORIES, true)) {
            if (strpos($mimeType, 'image/') !== 0) {
                throw new MediaException('invalid_media_type', 'This media category requires an image.', 422);
            }
            return 'cloudinary';
        }
        if (in_array($category, ['map', 'maps'], true) && strpos($mimeType, 'image/') === 0) {
            return 'cloudinary';
        }
        if (strpos($mimeType, 'image/') === 0 && $fileSize <= self::LARGE_IMAGE_BYTES) {
            return 'cloudinary';
        }
        return 'r2';
    }
}
