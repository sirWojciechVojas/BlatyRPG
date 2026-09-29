<?php

namespace App\Services\Audio;

use App\Services\Campaign\CampaignException;

final class ExternalAudioProviderRegistry
{
    private $providers;

    public function __construct(?array $providers = null)
    {
        $this->providers = $providers ?: [new YouTubeAudioProvider()];
    }

    public function resolve(string $url): array
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof ExternalAudioProvider && $provider->supports($url)) {
                return $provider->normalize($url);
            }
        }
        throw new CampaignException(
            'unsupported_audio_source',
            'This external audio provider is not supported.',
            422
        );
    }
}
