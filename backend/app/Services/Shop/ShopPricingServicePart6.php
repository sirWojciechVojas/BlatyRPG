<?php

namespace App\Services\Shop;

trait ShopPricingServicePart6
{
    private function roundPrice(float $value, int $step, string $mode): int
    {
        $ratio = $value / max(1, $step);
        if ($mode === 'up') {
            return (int) ceil($ratio) * $step;
        }
        if ($mode === 'down') {
            return (int) floor($ratio) * $step;
        }
        return (int) round($ratio) * $step;
    }

    private function modifierRecord(
        string $key,
        array $effect,
        array $result,
        array $extra = []
    ): array {
        return array_merge([
            'key' => $key,
            'enabled' => true,
            'configurable' => false,
            'applied' => true,
            'source' => (string) ($effect['source'] ?? ''),
            'reason' => (string) ($effect['reason'] ?? ''),
            'operation' => (string) ($effect['operation'] ?? 'multiplier'),
            'multiplier' => ($effect['operation'] ?? 'multiplier') === 'multiplier'
                ? $this->number($effect['multiplier'] ?? 1, 1)
                : null,
            'additiveDelta' => ($effect['operation'] ?? '') === 'additive'
                ? $this->number($effect['delta'] ?? 0)
                : null,
            'fixedValue' => ($effect['operation'] ?? '') === 'fixed'
                ? $this->number($effect['value'] ?? 0)
                : null,
            'before' => $result['before'],
            'after' => $result['after'],
            'delta' => $result['delta'],
            'metadata' => (array) ($effect['metadata'] ?? []),
        ], $extra);
    }

    private function applyEffect(float $current, array $effect): array
    {
        if (empty($effect['active'])) {
            return ['before' => $current, 'after' => $current, 'delta' => 0.0];
        }

        if (($effect['operation'] ?? 'multiplier') === 'additive') {
            $after = $current + $this->number($effect['delta'] ?? 0);
            return ['before' => $current, 'after' => $after, 'delta' => $after - $current];
        }

        if (($effect['operation'] ?? 'multiplier') === 'fixed') {
            $after = $this->number($effect['value'] ?? $current, $current);
            return ['before' => $current, 'after' => $after, 'delta' => $after - $current];
        }

        $after = $current * $this->number($effect['multiplier'] ?? 1, 1);
        return ['before' => $current, 'after' => $after, 'delta' => $after - $current];
    }

    private function multiplierEffect(string $source, float $multiplier, string $reason): array
    {
        return [
            'active' => true,
            'operation' => 'multiplier',
            'source' => $source,
            'multiplier' => $multiplier,
            'reason' => $reason,
        ];
    }

    private function additiveEffect(string $source, float $delta, string $reason): array
    {
        return [
            'active' => true,
            'operation' => 'additive',
            'source' => $source,
            'delta' => $delta,
            'reason' => $reason,
        ];
    }

    private function fixedEffect(string $source, float $value, string $reason): array
    {
        return [
            'active' => true,
            'operation' => 'fixed',
            'source' => $source,
            'value' => $value,
            'reason' => $reason,
        ];
    }

    private function inactiveEffect(string $source = '', string $reason = ''): array
    {
        return [
            'active' => false,
            'operation' => 'multiplier',
            'source' => $source,
            'multiplier' => 1.0,
            'reason' => $reason,
        ];
    }

    private function resolvePriceTier(float $basePrice, array $priceBands): string
    {
        if ($basePrice < (float) ($priceBands['cheapMax'] ?? 50)) {
            return 'cheap';
        }
        if ($basePrice < (float) ($priceBands['midMax'] ?? 200)) {
            return 'mid';
        }
        if ($basePrice < (float) ($priceBands['highMax'] ?? 800)) {
            return 'high';
        }
        return 'luxury';
    }

    private function resolveAvailabilityBand(int $quantity): string
    {
        if ($quantity <= 0) {
            return 'none';
        }
        if ($quantity === 1) {
            return 'scarce';
        }
        if ($quantity <= 3) {
            return 'low';
        }
        if ($quantity <= 6) {
            return 'medium';
        }
        return 'high';
    }

    private function resolveTemplateLegality(array $template): string
    {
        $explicit = strtolower((string) ($template['item_legality'] ?? $template['LEGALITY'] ?? $template['legality'] ?? ''));
        if (in_array($explicit, ['legal', 'grey', 'illegal'], true)) {
            return $explicit;
        }
        $text = $this->templateText($template);
        $itemClass = strtoupper((string) ($template['item_class'] ?? $template['ITEM_CLASS'] ?? ''));
        $itemGenre = strtoupper((string) ($template['item_genre'] ?? $template['ITEM_GENRE'] ?? ''));

        if (
            in_array($itemClass, ['POWDER'], true) ||
            in_array($itemGenre, ['TOXINS', 'FIREARMS', 'AMMUNITION'], true) ||
            $this->hasToken($text, ['truciz', 'zakazan', 'przemyt', 'kradz', 'nielegal', 'falsz', 'podrob', 'wytrych', 'kontraband'])
        ) {
            return 'illegal';
        }

        if (
            in_array($itemClass, ['MAGIC', 'JEWELLERY', 'WEAPON', 'ARMAMENT'], true) ||
            in_array($itemGenre, ['MAPS', 'BUFFS'], true) ||
            $this->hasToken($text, ['artefakt', 'rytual', 'amulet', 'proch', 'eliksir'])
        ) {
            return 'grey';
        }

        return 'legal';
    }

    private function resolveCounterfeitSensitivity(array $template): string
    {
        $explicit = strtolower((string) (
            $template['counterfeit_sensitivity'] ??
            $template['COUNTERFEIT_SENSITIVITY'] ??
            $template['counterfeitSensitivity'] ??
            ''
        ));
        if (in_array($explicit, ['low', 'medium', 'high'], true)) {
            return $explicit;
        }
        $itemClass = strtoupper((string) ($template['item_class'] ?? $template['ITEM_CLASS'] ?? ''));
        $itemGenre = strtoupper((string) ($template['item_genre'] ?? $template['ITEM_GENRE'] ?? ''));

        if (
            in_array($itemClass, ['JEWELLERY', 'MAGIC', 'MISC', 'POTION', 'ALCHEMY'], true) ||
            in_array($itemGenre, ['BOOKS', 'MAPS', 'BUFFS'], true)
        ) {
            return 'high';
        }
        if (in_array($itemClass, ['WEAPON', 'ARMOR', 'STATIONERY'], true)) {
            return 'medium';
        }
        return 'low';
    }

    private function templateText(array $template): string
    {
        return strtolower(implode(' ', [
            (string) ($template['name'] ?? $template['NAME'] ?? ''),
            (string) ($template['description'] ?? $template['DESCRIPTION'] ?? ''),
            (string) ($template['details'] ?? $template['DETAILS'] ?? ''),
            (string) ($template['item_genre'] ?? $template['ITEM_GENRE'] ?? ''),
            (string) ($template['item_class'] ?? $template['ITEM_CLASS'] ?? ''),
        ]));
    }

    private function hasToken(string $text, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($token !== '' && strpos($text, strtolower((string) $token)) !== false) {
                return true;
            }
        }
        return false;
    }

    private function normalizeUpperList(array $values): array
    {
        return array_values(array_filter(array_map(static function ($value): string {
            return strtoupper(trim((string) $value));
        }, $values)));
    }

    private function normalizeText($value): string
    {
        return strtolower(trim((string) $value));
    }

    private static function arrayInput($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return [];
        }
        return [$value];
    }

    private static function normalizeUpperListStatic($values): array
    {
        $result = [];
        foreach (self::arrayInput($values) as $value) {
            $normalized = strtoupper(trim((string) $value));
            if ($normalized !== '') {
                $result[$normalized] = $normalized;
            }
        }
        return array_values($result);
    }

    private static function normalizeLowerList($values, array $allowed): array
    {
        $allowedLookup = array_flip($allowed);
        $result = [];
        foreach (self::arrayInput($values) as $value) {
            $normalized = strtolower(trim((string) $value));
            if ($normalized !== '' && isset($allowedLookup[$normalized])) {
                $result[$normalized] = $normalized;
            }
        }
        return array_values($result);
    }
}
