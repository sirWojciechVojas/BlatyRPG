<?php

namespace App\Services\MapBuilder;

interface MapAiProviderInterface
{
    public function name(): string;

    public function configured(): bool;

    public function textModel(): string;

    public function imageModel(): string;

    public function propose(array $context): array;

    public function generateAsset(array $context): array;
}

