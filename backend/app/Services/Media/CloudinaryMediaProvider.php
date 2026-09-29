<?php

namespace App\Services\Media;

use InvalidArgumentException;

final class CloudinaryMediaProvider implements MediaProviderInterface
{
    public const VARIANTS = [
        'avatar-sm' => 'f_auto,q_auto,c_fill,g_auto,w_64,h_64',
        'avatar-md' => 'f_auto,q_auto,c_fill,g_auto,w_128,h_128',
        'token' => 'f_auto,q_auto,c_fill,g_auto,w_256,h_256',
        'portrait-card' => 'f_auto,q_auto,c_fill,g_auto,w_320,h_480',
        'portrait-large' => 'f_auto,q_auto,c_fill,g_auto,w_768,h_1152',
        'thumbnail' => 'f_auto,q_auto,c_fill,g_auto,w_240,h_240',
        'preview' => 'f_auto,q_auto,c_limit,w_1600,h_1200',
    ];

    private $cloudName;
    private $apiKey;
    private $apiSecret;

    public function __construct(?string $cloudName = null, ?string $apiKey = null, ?string $apiSecret = null)
    {
        $this->cloudName = trim((string) ($cloudName ?? getenv('CLOUDINARY_CLOUD_NAME') ?: 'dajzxmjyc'));
        $this->apiKey = trim((string) ($apiKey ?? getenv('CLOUDINARY_API_KEY') ?: ''));
        $this->apiSecret = trim((string) ($apiSecret ?? getenv('CLOUDINARY_API_SECRET') ?: ''));
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $this->cloudName)) {
            throw new InvalidArgumentException('Invalid Cloudinary cloud name.');
        }
    }

    public function name(): string
    {
        return 'cloudinary';
    }

    public function storageContainer(array $asset): string
    {
        $stored = trim((string) ($asset['provider_container'] ?? ''));
        $cloudName = $stored !== '' ? $stored : $this->cloudName;
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $cloudName)) {
            throw new MediaException('invalid_media_asset', 'Cloudinary cloud name is invalid.', 500);
        }
        return $cloudName;
    }

    public function prepareUpload(array $asset): array
    {
        $this->assertCredentials();
        $cloudName = $this->storageContainer($asset);
        $publicId = $this->normalizePublicId((string) ($asset['public_id'] ?? ''));
        if ($publicId === '') {
            throw new MediaException('invalid_media_asset', 'Cloudinary public ID is required.', 500);
        }

        $timestamp = time();
        $deliveryType = ($asset['visibility'] ?? 'private') === 'public' ? 'upload' : 'authenticated';
        $parameters = [
            'overwrite' => 'false',
            'public_id' => $publicId,
            'timestamp' => (string) $timestamp,
            'type' => $deliveryType,
        ];

        return [
            'method' => 'POST',
            'url' => sprintf(
                'https://api.cloudinary.com/v1_1/%s/%s/upload',
                rawurlencode($cloudName),
                rawurlencode((string) ($asset['resource_type'] ?? 'image'))
            ),
            'encoding' => 'multipart/form-data',
            'headers' => [],
            'fields' => $parameters + [
                'api_key' => $this->apiKey,
                'signature' => $this->signParameters($parameters),
            ],
            'expiresAt' => date(DATE_ATOM, $timestamp + 900),
        ];
    }

    public function completeUpload(array $asset, array $payload): array
    {
        $this->assertCredentials();
        $publicId = $this->normalizePublicId((string) ($payload['public_id'] ?? $payload['publicId'] ?? ''));
        if ($publicId === '' || !hash_equals((string) $asset['public_id'], $publicId)) {
            throw new MediaException('upload_result_mismatch', 'Cloudinary returned an unexpected public ID.', 422);
        }
        $version = (int) ($payload['version'] ?? 0);
        $signature = strtolower(trim((string) ($payload['signature'] ?? '')));
        $expected = sha1('public_id=' . $publicId . '&version=' . $version . $this->apiSecret);
        if ($version < 1 || $signature === '' || !hash_equals($expected, $signature)) {
            throw new MediaException('invalid_upload_signature', 'Cloudinary upload signature is invalid.', 422);
        }
        $assetId = trim((string) ($payload['asset_id'] ?? $payload['assetId'] ?? ''));
        if ($assetId === '') {
            throw new MediaException('invalid_upload_result', 'Cloudinary asset ID is missing.', 422);
        }

        return [
            'provider_asset_id' => $assetId,
            'public_id' => $publicId,
            'mime_type' => $this->mimeType($payload, (string) $asset['mime_type']),
            'file_size' => $this->positiveInt($payload['bytes'] ?? null, (int) ($asset['file_size'] ?? 0)),
            'width' => $this->nullablePositiveInt($payload['width'] ?? null),
            'height' => $this->nullablePositiveInt($payload['height'] ?? null),
            'duration' => $this->nullableDuration($payload['duration'] ?? null),
            'metadata' => [
                'delivery_type' => (string) ($payload['type'] ?? (($asset['visibility'] ?? '') === 'public' ? 'upload' : 'authenticated')),
                'format' => (string) ($payload['format'] ?? ''),
                'version' => $version,
            ],
        ];
    }

    public function uploadFile(array $asset, string $path): array
    {
        $this->assertCredentials();
        if (!is_file($path) || !is_readable($path)) {
            throw new MediaException('media_source_unavailable', 'The source file cannot be read.', 422);
        }
        $publicId = $this->normalizePublicId((string) ($asset['public_id'] ?? ''));
        $resourceType = (string) ($asset['resource_type'] ?? 'image');
        $timestamp = time();
        $parameters = [
            'overwrite' => 'false',
            'public_id' => $publicId,
            'timestamp' => (string) $timestamp,
            'type' => ($asset['visibility'] ?? '') === 'public' ? 'upload' : 'authenticated',
        ];
        $response = $this->request(
            'POST',
            sprintf('https://api.cloudinary.com/v1_1/%s/%s/upload', rawurlencode($this->storageContainer($asset)), rawurlencode($resourceType)),
            $parameters + [
                'api_key' => $this->apiKey,
                'signature' => $this->signParameters($parameters),
                'file' => new \CURLFile($path, (string) ($asset['mime_type'] ?? 'application/octet-stream'), basename($path)),
            ]
        );
        return $this->completeUpload($asset, $response);
    }

    public function verifyObject(array $asset): array
    {
        $this->assertCredentials();
        $resourceType = rawurlencode((string) ($asset['resource_type'] ?? 'image'));
        $deliveryType = rawurlencode($this->deliveryType($asset));
        $publicId = implode('/', array_map('rawurlencode', explode('/', $this->normalizePublicId((string) ($asset['public_id'] ?? '')))));
        $response = $this->request(
            'GET',
            sprintf('https://api.cloudinary.com/v1_1/%s/resources/%s/%s/%s', rawurlencode($this->storageContainer($asset)), $resourceType, $deliveryType, $publicId),
            null,
            true
        );
        return [
            'provider_asset_id' => $response['asset_id'] ?? null,
            'file_size' => isset($response['bytes']) ? (int) $response['bytes'] : null,
            'format' => $response['format'] ?? null,
            'width' => isset($response['width']) ? (int) $response['width'] : null,
            'height' => isset($response['height']) ? (int) $response['height'] : null,
        ];
    }

    public function deleteObject(array $asset): void
    {
        $this->assertCredentials();
        $publicId = $this->normalizePublicId((string) ($asset['public_id'] ?? ''));
        if ($publicId === '') {
            return;
        }
        $timestamp = time();
        $parameters = [
            'invalidate' => 'true',
            'public_id' => $publicId,
            'timestamp' => (string) $timestamp,
            'type' => $this->deliveryType($asset),
        ];
        $this->request(
            'POST',
            sprintf(
                'https://api.cloudinary.com/v1_1/%s/%s/destroy',
                rawurlencode($this->storageContainer($asset)),
                rawurlencode((string) ($asset['resource_type'] ?? 'image'))
            ),
            $parameters + ['api_key' => $this->apiKey, 'signature' => $this->signParameters($parameters)]
        );
    }

    public function deliveryUrl(array $asset, ?string $variant = null, int $ttl = 900): string
    {
        $cloudName = $this->storageContainer($asset);
        $publicId = $this->normalizePublicId((string) ($asset['public_id'] ?? ''));
        if ($publicId === '') {
            return '';
        }
        $resourceType = preg_replace('/[^a-z]/', '', (string) ($asset['resource_type'] ?? 'image')) ?: 'image';
        $transformation = $this->transformation($resourceType, $variant);
        $encodedId = implode('/', array_map('rawurlencode', explode('/', $publicId)));
        $deliveryType = $this->deliveryType($asset);
        $segments = array_values(array_filter([$transformation, $encodedId], static fn ($part): bool => $part !== ''));

        if ($deliveryType === 'upload' && ($asset['visibility'] ?? '') === 'public') {
            return sprintf(
                'https://res.cloudinary.com/%s/%s/upload/%s',
                rawurlencode($cloudName),
                $resourceType,
                implode('/', $segments)
            );
        }

        $this->assertCredentials();
        $assetId = trim((string) ($asset['provider_asset_id'] ?? ''));
        if ($assetId === '') {
            throw new MediaException('invalid_media_asset', 'Cloudinary asset ID is required for protected delivery.', 500);
        }
        $expiresAt = time() + max(60, min($ttl, 3600));
        $parameters = ['asset_id' => $assetId, 'expires_at' => (string) $expiresAt];
        return sprintf(
            'https://api.cloudinary.com/v1_1/%s/asset/download?%s',
            rawurlencode($cloudName),
            http_build_query($parameters + [
                'api_key' => $this->apiKey,
                'signature' => $this->signParameters($parameters),
            ], '', '&', PHP_QUERY_RFC3986)
        );
    }

    public function normalizePublicId(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $value)) {
            $path = rawurldecode((string) parse_url($value, PHP_URL_PATH));
            if (!preg_match('#/(?:image|video|raw)/(?:upload|authenticated)/(?:s--[^/]+--/)?(.+)$#', $path, $matches)) {
                throw new InvalidArgumentException('Only Cloudinary URLs can be converted to a public ID.');
            }
            $segments = array_values(array_filter(explode('/', $matches[1])));
            foreach ($segments as $index => $segment) {
                if (preg_match('/^v\d+$/', $segment)) {
                    $segments = array_slice($segments, $index + 1);
                    break;
                }
            }
            while ($segments && preg_match('/^(?:[a-z]_[^,]+)(?:,|$)/', $segments[0])) {
                array_shift($segments);
            }
            $value = implode('/', $segments);
        }
        $value = trim(rawurldecode($value), '/');
        $value = (string) preg_replace('/\.(?:avif|gif|jpe?g|png|webp)$/i', '', $value);
        if ($value !== '' && !preg_match('~^[^\s?#]+$~u', $value)) {
            throw new InvalidArgumentException('Invalid Cloudinary public ID.');
        }
        return $value;
    }

    private function transformation(string $resourceType, ?string $variant): string
    {
        if ($variant === null || $variant === '' || $resourceType !== 'image') {
            return '';
        }
        if (!array_key_exists($variant, self::VARIANTS)) {
            throw new MediaException('invalid_media_variant', 'Unsupported media variant.', 422);
        }
        return self::VARIANTS[$variant];
    }

    private function signParameters(array $parameters): string
    {
        ksort($parameters);
        $parts = [];
        foreach ($parameters as $key => $value) {
            $parts[] = $key . '=' . $value;
        }
        return sha1(implode('&', $parts) . $this->apiSecret);
    }

    private function deliveryType(array $asset): string
    {
        $metadata = is_array($asset['metadata'] ?? null) ? $asset['metadata'] : [];
        return (string) ($metadata['delivery_type'] ?? (($asset['visibility'] ?? '') === 'public' ? 'upload' : 'authenticated'));
    }

    private function request(string $method, string $url, ?array $fields = null, bool $basicAuth = false): array
    {
        if (!function_exists('curl_init')) {
            throw new MediaException('media_provider_unavailable', 'The cURL extension is required for Cloudinary.', 503);
        }
        $handle = curl_init($url);
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 300,
        ];
        if ($fields !== null) {
            $options[CURLOPT_POSTFIELDS] = $fields;
        }
        if ($basicAuth) {
            $options[CURLOPT_USERPWD] = $this->apiKey . ':' . $this->apiSecret;
        }
        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        $decoded = is_string($body) ? json_decode($body, true) : null;
        if ($body === false || $status < 200 || $status >= 300 || !is_array($decoded)) {
            $providerMessage = is_array($decoded) ? (string) ($decoded['error']['message'] ?? '') : '';
            throw new MediaException(
                'media_provider_unavailable',
                'Cloudinary operation failed.' . ($providerMessage !== '' ? ' ' . $providerMessage : ($error !== '' ? ' ' . $error : '')),
                503,
                ['providerStatus' => $status]
            );
        }
        return $decoded;
    }

    private function assertCredentials(): void
    {
        if ($this->apiKey === '' || $this->apiSecret === '') {
            throw new MediaException('media_provider_unavailable', 'Cloudinary credentials are not configured.', 503);
        }
    }

    private function assertSecret(): void
    {
        if ($this->apiSecret === '') {
            throw new MediaException('media_provider_unavailable', 'Cloudinary signing secret is not configured.', 503);
        }
    }

    private function mimeType(array $payload, string $fallback): string
    {
        $format = strtolower(trim((string) ($payload['format'] ?? '')));
        return $format !== '' ? 'image/' . ($format === 'jpg' ? 'jpeg' : $format) : $fallback;
    }

    private function positiveInt($value, int $fallback): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $value === false ? $fallback : (int) $value;
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
