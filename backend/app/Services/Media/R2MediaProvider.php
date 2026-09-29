<?php

namespace App\Services\Media;

final class R2MediaProvider implements MediaProviderInterface
{
    private $endpoint;
    private $privateBucket;
    private $publicBucket;
    private $accessKey;
    private $secretKey;
    private $publicBaseUrl;
    private $headResolver;

    public function __construct(
        ?string $endpoint = null,
        ?string $bucket = null,
        ?string $accessKey = null,
        ?string $secretKey = null,
        ?string $publicBaseUrl = null,
        ?callable $headResolver = null,
        ?string $publicBucket = null
    ) {
        $accountId = trim((string) (getenv('R2_ACCOUNT_ID') ?: ''));
        $defaultEndpoint = $accountId !== '' ? 'https://' . $accountId . '.r2.cloudflarestorage.com' : '';
        $this->endpoint = rtrim(trim((string) ($endpoint ?? getenv('R2_ENDPOINT') ?: $defaultEndpoint)), '/');
        if ($bucket !== null) {
            $this->privateBucket = trim($bucket);
            $this->publicBucket = trim((string) ($publicBucket ?? $bucket));
        } else {
            $legacyBucket = trim((string) (getenv('R2_BUCKET') ?: ''));
            $this->privateBucket = trim((string) (getenv('R2_PRIVATE_BUCKET') ?: $legacyBucket));
            $this->publicBucket = trim((string) ($publicBucket ?? getenv('R2_PUBLIC_BUCKET') ?: $legacyBucket));
        }
        $this->accessKey = trim((string) ($accessKey ?? getenv('R2_ACCESS_KEY_ID') ?: ''));
        $this->secretKey = trim((string) ($secretKey ?? getenv('R2_SECRET_ACCESS_KEY') ?: ''));
        $this->publicBaseUrl = rtrim(trim((string) ($publicBaseUrl ?? getenv('R2_PUBLIC_BASE_URL') ?: '')), '/');
        $this->headResolver = $headResolver;
    }

    public function name(): string
    {
        return 'r2';
    }

    public function storageContainer(array $asset): string
    {
        $stored = trim((string) ($asset['provider_container'] ?? ''));
        $bucket = $stored !== ''
            ? $stored
            : (($asset['visibility'] ?? 'private') === 'public'
                ? $this->publicBucket
                : $this->privateBucket);
        if (!$this->validBucket($bucket)) {
            throw new MediaException('media_provider_unavailable', 'Cloudflare R2 bucket is not configured.', 503);
        }
        return $bucket;
    }

    public function prepareUpload(array $asset): array
    {
        $bucket = $this->storageContainer($asset);
        $this->assertConfigured($bucket);
        $expires = 900;
        return [
            'method' => 'PUT',
            'url' => $this->presignedUrl(
                'PUT',
                (string) $asset['provider_asset_id'],
                $expires,
                null,
                $bucket
            ),
            'encoding' => 'binary',
            'headers' => ['Content-Type' => (string) $asset['mime_type']],
            'fields' => [],
            'expiresAt' => date(DATE_ATOM, time() + $expires),
        ];
    }

    public function completeUpload(array $asset, array $payload): array
    {
        $etag = trim((string) ($payload['etag'] ?? $payload['eTag'] ?? ''), " \t\n\r\0\x0B\"");
        if ($etag === '' || !preg_match('/^[a-zA-Z0-9:+\/=._-]{8,128}$/', $etag)) {
            throw new MediaException('invalid_upload_result', 'A valid R2 ETag is required.', 422);
        }
        $key = trim((string) ($asset['provider_asset_id'] ?? ''));
        if ($key === '') {
            throw new MediaException('invalid_media_asset', 'R2 object key is missing.', 500);
        }
        $bucket = $this->storageContainer($asset);
        $remote = $this->headObject($key, $bucket);
        $remoteEtag = trim((string) ($remote['etag'] ?? ''), " \t\n\r\0\x0B\"");
        $size = filter_var($remote['file_size'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($remoteEtag === '' || $size === false || !hash_equals($remoteEtag, $etag)) {
            throw new MediaException('upload_result_mismatch', 'R2 upload metadata could not be verified.', 422);
        }
        if ((int) ($asset['file_size'] ?? 0) > 0 && (int) $size !== (int) $asset['file_size']) {
            throw new MediaException('upload_size_mismatch', 'Uploaded file size does not match the authorized size.', 422);
        }
        return [
            'provider_container' => $bucket,
            'provider_asset_id' => $key,
            'public_id' => null,
            'mime_type' => (string) $asset['mime_type'],
            'file_size' => (int) $size,
            'width' => $this->nullablePositiveInt($payload['width'] ?? null),
            'height' => $this->nullablePositiveInt($payload['height'] ?? null),
            'duration' => $this->nullableDuration($payload['duration'] ?? null),
            'metadata' => ['etag' => $etag],
        ];
    }

    public function uploadFile(array $asset, string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new MediaException('media_source_unavailable', 'The source file cannot be read.', 422);
        }
        $key = trim((string) ($asset['provider_asset_id'] ?? ''));
        $bucket = $this->storageContainer($asset);
        $size = (int) filesize($path);
        $this->requestFile('PUT', $key, $bucket, $path, (string) ($asset['mime_type'] ?? 'application/octet-stream'));
        $remote = $this->headObject($key, $bucket);
        if ((int) ($remote['file_size'] ?? 0) !== $size) {
            throw new MediaException('upload_size_mismatch', 'Uploaded R2 object has an unexpected size.', 503);
        }
        return [
            'provider_container' => $bucket,
            'provider_asset_id' => $key,
            'public_id' => null,
            'mime_type' => (string) ($asset['mime_type'] ?? 'application/octet-stream'),
            'file_size' => $size,
            'width' => $asset['width'] ?? null,
            'height' => $asset['height'] ?? null,
            'duration' => $asset['duration'] ?? null,
            'metadata' => ['etag' => trim((string) ($remote['etag'] ?? ''), '"')],
        ];
    }

    public function verifyObject(array $asset): array
    {
        $key = trim((string) ($asset['provider_asset_id'] ?? ''));
        if ($key === '') {
            throw new MediaException('invalid_media_asset', 'R2 object key is missing.', 500);
        }
        return $this->headObject($key, $this->storageContainer($asset));
    }

    public function deleteObject(array $asset): void
    {
        $key = trim((string) ($asset['provider_asset_id'] ?? ''));
        if ($key === '') {
            return;
        }
        $this->requestFile('DELETE', $key, $this->storageContainer($asset));
    }

    public function deliveryUrl(array $asset, ?string $variant = null, int $ttl = 900): string
    {
        $key = (string) ($asset['provider_asset_id'] ?? '');
        if ($key === '') {
            return '';
        }
        if ($variant !== null && $variant !== '') {
            throw new MediaException('invalid_media_variant', 'R2 assets do not support image variants.', 422);
        }
        $bucket = $this->storageContainer($asset);
        if (($asset['visibility'] ?? '') === 'public'
            && $bucket === $this->publicBucket
            && $this->publicBaseUrl !== '') {
            return $this->publicBaseUrl . '/' . $this->encodePath($key);
        }
        $this->assertConfigured($bucket);
        return $this->presignedUrl('GET', $key, max(60, min($ttl, 3600)), null, $bucket);
    }

    public function presignedUrl(
        string $method,
        string $key,
        int $expires,
        ?int $timestamp = null,
        ?string $bucket = null
    ): string {
        $bucket = trim((string) ($bucket ?? $this->privateBucket));
        $this->assertConfigured($bucket);
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'HEAD', 'PUT', 'DELETE'], true)) {
            throw new MediaException('invalid_media_request', 'Unsupported R2 operation.', 500);
        }
        $timestamp = $timestamp ?? time();
        $amzDate = gmdate('Ymd\THis\Z', $timestamp);
        $date = gmdate('Ymd', $timestamp);
        $scope = $date . '/auto/s3/aws4_request';
        $endpoint = parse_url($this->endpoint);
        $host = (string) ($endpoint['host'] ?? '');
        if (!empty($endpoint['port'])) {
            $host .= ':' . (int) $endpoint['port'];
        }
        $basePath = trim((string) ($endpoint['path'] ?? ''), '/');
        $canonicalUri = '/' . $this->encodePath(implode('/', array_filter(
            [$basePath, $bucket, $key],
            static fn ($part): bool => $part !== ''
        )));
        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->accessKey . '/' . $scope,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string) max(60, min($expires, 3600)),
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($query);
        $canonicalQuery = $this->query($query);
        $canonicalRequest = $method . "\n" . $canonicalUri . "\n" . $canonicalQuery . "\n"
            . 'host:' . $host . "\n\n" . 'host' . "\nUNSIGNED-PAYLOAD";
        $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
        $dateKey = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $regionKey = hash_hmac('sha256', 'auto', $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $serviceKey, true);
        $query['X-Amz-Signature'] = hash_hmac('sha256', $stringToSign, $signingKey);
        ksort($query);
        $scheme = (string) ($endpoint['scheme'] ?? 'https');
        return $scheme . '://' . $host . $canonicalUri . '?' . $this->query($query);
    }

    private function query(array $parameters): string
    {
        $parts = [];
        foreach ($parameters as $key => $value) {
            $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }
        return implode('&', $parts);
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', trim($path, '/'))));
    }

    private function headObject(string $key, string $bucket): array
    {
        if (is_callable($this->headResolver)) {
            return (array) call_user_func($this->headResolver, $key, $bucket);
        }
        if (!function_exists('curl_init')) {
            throw new MediaException('media_provider_unavailable', 'The cURL extension is required for R2.', 503);
        }

        $headers = [];
        $handle = curl_init($this->presignedUrl('HEAD', $key, 60, null, $bucket));
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => 'HEAD',
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                $separator = strpos($line, ':');
                if ($separator !== false) {
                    $headers[strtolower(trim(substr($line, 0, $separator)))] = trim(substr($line, $separator + 1));
                }
                return strlen($line);
            },
        ]);
        $result = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($result === false || $status < 200 || $status >= 300) {
            $message = $status === 404
                ? 'The uploaded R2 object was not found.'
                : 'Could not verify the uploaded R2 object.';
            throw new MediaException(
                $status === 404 ? 'media_upload_missing' : 'media_provider_unavailable',
                $message . ($error !== '' ? ' ' . $error : ''),
                $status === 404 ? 409 : 503
            );
        }

        return [
            'etag' => $headers['etag'] ?? '',
            'file_size' => $headers['content-length'] ?? null,
        ];
    }

    private function requestFile(
        string $method,
        string $key,
        string $bucket,
        ?string $path = null,
        string $mimeType = 'application/octet-stream'
    ): void {
        if (!function_exists('curl_init')) {
            throw new MediaException('media_provider_unavailable', 'The cURL extension is required for R2.', 503);
        }
        $handle = curl_init($this->presignedUrl($method, $key, 300, null, $bucket));
        $stream = null;
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 300,
        ];
        if ($path !== null) {
            $stream = fopen($path, 'rb');
            if ($stream === false) {
                throw new MediaException('media_source_unavailable', 'The source file cannot be opened.', 422);
            }
            $options[CURLOPT_UPLOAD] = true;
            $options[CURLOPT_INFILE] = $stream;
            $options[CURLOPT_INFILESIZE] = (int) filesize($path);
            $options[CURLOPT_HTTPHEADER] = ['Content-Type: ' . $mimeType];
        }
        curl_setopt_array($handle, $options);
        $result = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (is_resource($stream)) {
            fclose($stream);
        }
        if ($result === false || ($status !== 404 && ($status < 200 || $status >= 300))) {
            throw new MediaException(
                'media_provider_unavailable',
                'Cloudflare R2 operation failed.' . ($error !== '' ? ' ' . $error : ''),
                503,
                ['providerStatus' => $status]
            );
        }
    }

    private function assertConfigured(string $bucket): void
    {
        if ($this->endpoint === '' || !$this->validBucket($bucket)
            || $this->accessKey === '' || $this->secretKey === '') {
            throw new MediaException('media_provider_unavailable', 'Cloudflare R2 is not configured.', 503);
        }
        $scheme = strtolower((string) parse_url($this->endpoint, PHP_URL_SCHEME));
        if (!in_array($scheme, ['https', 'http'], true) || parse_url($this->endpoint, PHP_URL_HOST) === null) {
            throw new MediaException('media_provider_unavailable', 'Cloudflare R2 endpoint is invalid.', 503);
        }
    }

    private function validBucket(string $bucket): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $bucket);
    }

    private function nullablePositiveInt($value): ?int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $value === false ? null : (int) $value;
    }

    private function nullableDuration($value): ?float
    {
        return is_numeric($value) && (float) $value >= 0 ? round((float) $value, 3) : null;
    }
}
