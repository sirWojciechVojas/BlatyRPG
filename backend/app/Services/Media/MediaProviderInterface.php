<?php

namespace App\Services\Media;

interface MediaProviderInterface
{
    public function name(): string;

    /** Resolves the provider-side cloud or bucket persisted with the asset. */
    public function storageContainer(array $asset): string;

    /** Returns provider-neutral direct-upload instructions. */
    public function prepareUpload(array $asset): array;

    /** Validates a browser upload result and returns authoritative storage metadata. */
    public function completeUpload(array $asset, array $payload): array;

    /** Uploads a local server-side file and returns authoritative storage metadata. */
    public function uploadFile(array $asset, string $path): array;

    /** Verifies that the provider object exists and returns its current metadata. */
    public function verifyObject(array $asset): array;

    /** Permanently removes the provider object. Missing objects are treated as removed. */
    public function deleteObject(array $asset): void;

    /** Builds a delivery URL. URLs are never persisted in media_assets. */
    public function deliveryUrl(array $asset, ?string $variant = null, int $ttl = 900): string;
}
