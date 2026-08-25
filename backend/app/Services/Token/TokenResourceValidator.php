<?php

namespace App\Services\Token;

final class TokenResourceValidator
{
    private const BAR_DEFAULTS = [
        ['enabled' => true, 'label' => 'HP', 'value' => 0, 'max' => 0, 'color' => '#d95d55', 'movementSource' => false],
        ['enabled' => true, 'label' => 'PR', 'value' => 6, 'max' => 6, 'color' => '#4caf72', 'movementSource' => true],
        ['enabled' => false, 'label' => '', 'value' => 0, 'max' => 0, 'color' => '#4f91d9', 'movementSource' => false],
        ['enabled' => false, 'label' => '', 'value' => 0, 'max' => 0, 'color' => '#d5a64f', 'movementSource' => false],
    ];
    private const LEGACY_BAR_COLORS = ['#4caf72', '#d95d55', '#4f91d9', '#d5a64f'];
    private const POSITIONS = [
        'top-left', 'top-center', 'top-right',
        'bottom-left', 'bottom-center', 'bottom-right',
    ];

    public static function validate($value): array
    {
        if (!is_array($value)) return self::invalid('Resources must be an object.');
        if (array_diff(array_keys($value), ['bars', 'bubbles'])) {
            return self::invalid('Resources contain an unsupported field.');
        }
        $bars = $value['bars'] ?? [];
        $bubbles = $value['bubbles'] ?? [];
        if (!is_array($bars) || !self::isList($bars) || count($bars) > 4) {
            return self::invalid('Resources may contain at most four bars.');
        }
        if (!is_array($bubbles) || !self::isList($bubbles) || count($bubbles) > 3) {
            return self::invalid('Resources may contain at most three bubbles.');
        }
        if (self::legacyEmptyBars($bars)) $bars = [];
        $result = self::defaults();
        $explicitSources = array_filter($bars, static fn ($bar): bool =>
            is_array($bar) && ($bar['movementSource'] ?? null) === true
        );
        if (count($explicitSources) > 1) {
            return self::invalid('Only one bar may control movement points.');
        }
        if ($explicitSources) {
            foreach ($result['bars'] as &$bar) $bar['movementSource'] = false;
            unset($bar);
        }
        foreach ($bars as $index => $bar) {
            $parsed = self::bar($bar, $index);
            if (!$parsed['valid']) return $parsed;
            $result['bars'][$index] = $parsed['data'];
        }
        foreach ($bubbles as $index => $bubble) {
            $parsed = self::bubble($bubble, $index);
            if (!$parsed['valid']) return $parsed;
            $result['bubbles'][$index] = $parsed['data'];
        }
        return ['valid' => true, 'data' => $result, 'error' => null];
    }

    public static function stored($value): array
    {
        $result = self::validate(is_array($value) ? $value : []);
        return $result['valid'] ? $result['data'] : self::defaults();
    }

    public static function hasBindings(array $resources): bool
    {
        foreach ($resources['bars'] ?? [] as $bar) {
            if (!empty($bar['attributePath']) || !empty($bar['maxAttributePath'])) return true;
        }
        foreach ($resources['bubbles'] ?? [] as $bubble) {
            if (!empty($bubble['attributePath'])) return true;
        }
        return false;
    }

    private static function bar($value, int $index): array
    {
        $allowed = [
            'enabled', 'label', 'value', 'max', 'color',
            'attributePath', 'maxAttributePath', 'movementSource',
        ];
        if (!is_array($value) || array_diff(array_keys($value), $allowed)) {
            return self::invalid('A bar contains an unsupported field.');
        }
        $default = self::defaults()['bars'][$index];
        $parsed = self::entry($value, $default, ['attributePath', 'maxAttributePath']);
        if (!$parsed['valid']) return $parsed;
        $movementSource = $value['movementSource'] ?? $default['movementSource'];
        if (!is_bool($movementSource)) {
            return self::invalid('Movement source must be boolean.');
        }
        $parsed['data']['movementSource'] = $movementSource;
        if ($movementSource) $parsed['data']['enabled'] = true;
        return $parsed;
    }

    private static function bubble($value, int $index): array
    {
        $allowed = [
            'enabled', 'label', 'value', 'position', 'attributePath',
            'linkedBarIndex',
        ];
        if (!is_array($value) || array_diff(array_keys($value), $allowed)) {
            return self::invalid('A bubble contains an unsupported field.');
        }
        $default = self::defaults()['bubbles'][$index];
        $parsed = self::entry($value, $default, ['attributePath'], false);
        if (!$parsed['valid']) return $parsed;
        $position = (string) ($value['position'] ?? $default['position']);
        if (!in_array($position, self::POSITIONS, true)) {
            return self::invalid('A bubble position is invalid.');
        }
        $parsed['data']['position'] = $position;
        $linked = $value['linkedBarIndex'] ?? $default['linkedBarIndex'];
        if ($linked !== null && (!is_int($linked) || $linked < 0 || $linked > 3)) {
            return self::invalid('A linked bar index is invalid.');
        }
        if ($linked !== null && $parsed['data']['attributePath'] !== '') {
            return self::invalid('A linked bubble cannot also bind an actor attribute.');
        }
        $parsed['data']['linkedBarIndex'] = $linked;
        return $parsed;
    }

    private static function entry(
        array $value,
        array $default,
        array $paths,
        bool $withMax = true
    ): array {
        $enabled = $value['enabled'] ?? $default['enabled'];
        $label = trim((string) ($value['label'] ?? $default['label']));
        if (!is_bool($enabled)) return self::invalid('Resource enabled must be boolean.');
        if (mb_strlen($label) > 30) return self::invalid('Resource label is too long.');
        $numbers = $withMax ? ['value', 'max'] : ['value'];
        $data = $default + ['enabled' => false, 'label' => ''];
        $data['enabled'] = $enabled;
        $data['label'] = $label;
        foreach ($numbers as $field) {
            $number = $value[$field] ?? $default[$field];
            if (!is_numeric($number) || !is_finite((float) $number)) {
                return self::invalid('Resource values must be finite numbers.');
            }
            $data[$field] = round(max(-1000000000, min(1000000000, (float) $number)), 3);
        }
        if (isset($default['color'])) {
            $color = strtolower((string) ($value['color'] ?? $default['color']));
            if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
                return self::invalid('A bar color is invalid.');
            }
            $data['color'] = $color;
        }
        foreach ($paths as $field) {
            $path = trim((string) ($value[$field] ?? ''));
            if (!self::safePath($path)) return self::invalid('An attribute path is invalid.');
            $data[$field] = $path;
        }
        return ['valid' => true, 'data' => $data, 'error' => null];
    }

    private static function safePath(string $path): bool
    {
        if ($path === '') return true;
        if (strlen($path) > 180 || !preg_match('/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+){0,7}$/', $path)) {
            return false;
        }
        $blocked = ['__proto__', 'prototype', 'constructor'];
        return !array_intersect(array_map('strtolower', explode('.', $path)), $blocked);
    }

    private static function isList(array $value): bool
    {
        return !$value || array_keys($value) === range(0, count($value) - 1);
    }

    private static function legacyEmptyBars(array $bars): bool
    {
        if (count($bars) !== count(self::LEGACY_BAR_COLORS)) return false;
        foreach ($bars as $index => $bar) {
            if (!is_array($bar)
                || !empty($bar['enabled'])
                || trim((string) ($bar['label'] ?? '')) !== ''
                || (float) ($bar['value'] ?? 0) !== 0.0
                || (float) ($bar['max'] ?? 0) !== 0.0
                || trim((string) ($bar['attributePath'] ?? '')) !== ''
                || trim((string) ($bar['maxAttributePath'] ?? '')) !== ''
                || strtolower((string) ($bar['color'] ?? '')) !== self::LEGACY_BAR_COLORS[$index]
            ) return false;
        }
        return true;
    }

    private static function defaults(): array
    {
        $bars = [];
        foreach (self::BAR_DEFAULTS as $default) {
            $bars[] = $default + [
                'attributePath' => '', 'maxAttributePath' => '',
            ];
        }
        $bubbles = [];
        foreach (['top-left', 'top-center', 'top-right'] as $position) {
            $bubbles[] = [
                'enabled' => false, 'label' => '', 'value' => 0,
                'position' => $position, 'attributePath' => '',
                'linkedBarIndex' => null,
            ];
        }
        return ['bars' => $bars, 'bubbles' => $bubbles];
    }

    private static function invalid(string $message): array
    {
        return ['valid' => false, 'data' => null, 'error' => $message];
    }
}
