<?php

namespace App\Services\Shop;

trait ShopPricingServicePart7
{
    private static function normalizeFreeLowerList($values): array
    {
        $result = [];
        foreach (self::arrayInput($values) as $value) {
            $normalized = substr(strtolower(trim((string) $value)), 0, 64);
            if ($normalized !== '') {
                $result[$normalized] = $normalized;
            }
        }
        return array_values($result);
    }

    private static function numberStatic($value, float $fallback = 0.0): float
    {
        return is_numeric($value) ? (float) $value : $fallback;
    }

    private static function clampStatic(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    private function number($value, float $fallback = 0.0): float
    {
        return is_numeric($value) ? (float) $value : $fallback;
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    private function typeNode(string $typeId): ?array
    {
        if ($typeId === '') {
            return null;
        }
        $this->ensureLookups();
        return $this->typeLookup[$typeId] ?? null;
    }

    private function worldProfile(string $profileId): ?array
    {
        $this->ensureLookups();
        return $this->worldLookup[$profileId] ?? null;
    }

    private function ensureLookups(): void
    {
        if ($this->typeLookup !== null && $this->worldLookup !== null) {
            return;
        }

        $this->typeLookup = [];
        foreach ($this->catalogService->getCatalogNetwork() as $node) {
            if (($node['level'] ?? '') === 'type') {
                $this->typeLookup[(string) $node['id']] = $node;
            }
        }

        $this->worldLookup = [];
        foreach ($this->catalogService->getWorldProfiles() as $profile) {
            $this->worldLookup[(string) $profile['id']] = $profile;
        }
    }
}
