<?php

namespace App\Services\MapBuilder;

final class MapAiProviderRegistry
{
    private $providers;
    private $selected;

    public function __construct(?array $providers = null, ?string $selected = null)
    {
        $this->providers = $providers ?: ['openai' => new OpenAiMapProvider()];
        $this->selected = strtolower(trim((string) ($selected ?? getenv('MAP_AI_PROVIDER') ?: 'openai')));
    }

    public function provider(): MapAiProviderInterface
    {
        $provider = $this->providers[$this->selected] ?? null;
        if (!$provider instanceof MapAiProviderInterface) {
            throw new MapBuilderException('ai_provider_unknown', 'Configured map AI provider is unavailable.', 503);
        }
        return $provider;
    }

    public function status(): array
    {
        try {
            $provider = $this->provider();
        } catch (MapBuilderException $exception) {
            return ['available' => false, 'provider' => $this->selected, 'reason' => 'provider_unknown'];
        }
        return [
            'available' => $provider->configured(),
            'provider' => $provider->name(),
            'reason' => $provider->configured() ? null : 'not_configured',
            'textModel' => $provider->configured() ? $provider->textModel() : null,
            'imageModel' => $provider->configured() ? $provider->imageModel() : null,
        ];
    }
}
