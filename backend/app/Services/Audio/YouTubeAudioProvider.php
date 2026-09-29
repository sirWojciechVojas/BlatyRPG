<?php

namespace App\Services\Audio;

use App\Services\Campaign\CampaignException;

/** Metadata-only adapter. Playback remains in the official YouTube iframe API. */
final class YouTubeAudioProvider implements ExternalAudioProvider
{
    public function name(): string
    {
        return 'youtube';
    }

    public function supports(string $url): bool
    {
        return $this->videoId($url) !== null;
    }

    public function normalize(string $url): array
    {
        $videoId = $this->videoId($url);
        if ($videoId === null) {
            throw new CampaignException(
                'unsupported_audio_source',
                'The YouTube URL is invalid or unsupported.',
                422
            );
        }
        return [
            'provider' => $this->name(),
            'reference' => $videoId,
            'url' => 'https://www.youtube.com/watch?v=' . $videoId,
            'thumbnailUrl' => 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg',
        ];
    }

    private function videoId(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host);
        $candidate = null;
        if ($host === 'youtu.be') {
            $candidate = trim((string) ($parts['path'] ?? ''), '/');
        } elseif (in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com'], true)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $candidate = $query['v'] ?? null;
            if (!$candidate && preg_match('#^/(?:embed|shorts)/([^/]+)#', (string) ($parts['path'] ?? ''), $matches)) {
                $candidate = $matches[1];
            }
        }
        $candidate = trim((string) $candidate);
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate) ? $candidate : null;
    }
}
