<?php

namespace App\Services;

use InvalidArgumentException;

final class CloudinaryCharacterAssetService
{
    public const TYPES = ['avatar', 'portrait', 'token', 'fullbody'];

    private const TRANSFORMATIONS = [
        'avatar' => 'f_auto,q_auto,c_fill,g_auto,w_128,h_128',
        'portrait' => 'f_auto,q_auto,c_fill,g_auto,w_512,h_768',
        'token' => 'f_auto,q_auto,c_fill,g_auto,w_256,h_256',
        'fullbody' => 'f_auto,q_auto,c_fit,w_1024,h_1536',
    ];

    private $cloudName;

    public function __construct(?string $cloudName = null)
    {
        $resolved = trim((string) ($cloudName ?? getenv('CLOUDINARY_CLOUD_NAME') ?: 'dajzxmjyc'));
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $resolved)) {
            throw new InvalidArgumentException('Invalid Cloudinary cloud name.');
        }
        $this->cloudName = $resolved;
    }

    public function canonicalPublicId(int $assetSetId, string $type): string
    {
        $this->assertType($type);
        if ($assetSetId < 1) {
            throw new InvalidArgumentException('Asset set ID must be positive.');
        }

        return sprintf('character-assets/%06d/%s', $assetSetId, $type);
    }

    public function url(string $publicId, string $type): string
    {
        $this->assertType($type);
        $normalized = $this->normalizePublicId($publicId);
        if ($normalized === '') {
            return '';
        }

        $encodedPublicId = implode('/', array_map('rawurlencode', explode('/', $normalized)));
        return sprintf(
            'https://res.cloudinary.com/%s/image/upload/%s/%s',
            rawurlencode($this->cloudName),
            self::TRANSFORMATIONS[$type],
            $encodedPublicId
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
            $marker = '/image/upload/';
            $position = strpos($path, $marker);
            if ($position === false) {
                throw new InvalidArgumentException('Only Cloudinary image URLs can be converted to a public ID.');
            }
            $segments = array_values(array_filter(explode('/', substr($path, $position + strlen($marker)))));
            $versionIndex = null;
            foreach ($segments as $index => $segment) {
                if (preg_match('/^v\d+$/', $segment)) {
                    $versionIndex = $index;
                    break;
                }
            }
            // A version is the reliable boundary between optional Cloudinary
            // transformations and the actual public ID. Legacy URLs without a
            // version did not contain transformations, so all segments belong
            // to the public ID in that case.
            if ($versionIndex !== null) {
                $segments = array_slice($segments, $versionIndex + 1);
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

    private function assertType(string $type): void
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported character asset type: ' . $type);
        }
    }
}
