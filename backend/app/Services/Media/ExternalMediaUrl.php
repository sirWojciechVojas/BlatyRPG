<?php

namespace App\Services\Media;

/**
 * Normalizes remote media references without ever requesting the remote host.
 * The reference can safely be catalogued and deduplicated, while a later,
 * explicitly authorised import may copy its bytes to owned storage.
 */
final class ExternalMediaUrl
{
    public static function canonicalize(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 2048 || strpos($value, '//') === 0) {
            return null;
        }

        $parts = parse_url($value);
        if (!is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        // URL fragments never identify a different remote object. Removing them
        // also prevents duplicate catalogue records for the same map image.
        return preg_replace('/#.*$/', '', $value) ?: null;
    }

    public static function fingerprint(string $canonicalUrl): string
    {
        return hash('sha256', $canonicalUrl);
    }

    public static function filename(string $canonicalUrl): string
    {
        $path = (string) parse_url($canonicalUrl, PHP_URL_PATH);
        $filename = basename(rawurldecode($path));
        $filename = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $filename));
        return mb_substr($filename !== '' && $filename !== '/' ? $filename : 'external-map', 0, 255);
    }
}
