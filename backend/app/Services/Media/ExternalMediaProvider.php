<?php

namespace App\Services\Media;

/** Read-only provider for catalogue records that point to an external HTTP(S) URL. */
final class ExternalMediaProvider implements MediaProviderInterface
{
    public function name(): string
    {
        return 'external';
    }

    public function storageContainer(array $asset): string
    {
        return 'external';
    }

    public function prepareUpload(array $asset): array
    {
        throw $this->readOnly();
    }

    public function completeUpload(array $asset, array $payload): array
    {
        throw $this->readOnly();
    }

    public function uploadFile(array $asset, string $path): array
    {
        throw $this->readOnly();
    }

    public function verifyObject(array $asset): array
    {
        // Do not probe arbitrary URLs server-side: that would turn availability
        // checks into an SSRF surface. An explicit future import may verify bytes.
        return [
            'availability_status' => (string) ($asset['availability_status'] ?? 'unknown'),
            'availability_checked_at' => $asset['availability_checked_at'] ?? null,
        ];
    }

    public function deleteObject(array $asset): void
    {
        // The remote object is not owned by BlatyRPG. Only the registry row is removed.
    }

    public function deliveryUrl(array $asset, ?string $variant = null, int $ttl = 900): string
    {
        $url = ExternalMediaUrl::canonicalize((string) ($asset['source_url'] ?? ''));
        if ($url === null) {
            throw new MediaException('external_source_unavailable', 'The external media source is invalid.', 503);
        }
        return $url;
    }

    private function readOnly(): MediaException
    {
        return new MediaException(
            'external_provider_read_only',
            'External media references cannot receive file uploads.',
            409
        );
    }
}
