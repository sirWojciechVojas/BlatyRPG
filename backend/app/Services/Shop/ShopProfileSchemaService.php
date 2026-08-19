<?php

namespace App\Services\Shop;

class ShopProfileSchemaService
{
    public static function defaultMarketSettings(): array
    {
        return [
            'demandLevel' => 'normal',
            'availabilityBias' => 0,
            'buybackBudget' => null,
            'maxBuybackItemValue' => null,
            'expensiveStockLimit' => null,
            'localCategories' => [],
            'importedCategories' => [],
            'reputationByActor' => [],
        ];
    }

    public static function normalizeSettings($input): array
    {
        $raw = is_array($input) ? $input : [];
        $result = self::defaultMarketSettings();
        $result['demandLevel'] = self::enum($raw['demandLevel'] ?? 'normal', [
            'very_low', 'low', 'normal', 'high', 'extreme',
        ], 'normal');
        $result['availabilityBias'] = self::number($raw['availabilityBias'] ?? 0, -50, 50);
        foreach (['buybackBudget', 'maxBuybackItemValue', 'expensiveStockLimit'] as $key) {
            $value = $raw[$key] ?? null;
            $result[$key] = $value === null || $value === ''
                ? null
                : self::number($value, 0, 100000000);
        }
        $result['localCategories'] = self::stringList($raw['localCategories'] ?? [], 30, 64);
        $result['importedCategories'] = self::stringList($raw['importedCategories'] ?? [], 30, 64);
        $reputations = is_array($raw['reputationByActor'] ?? null) ? $raw['reputationByActor'] : [];
        foreach (array_slice($reputations, 0, 100, true) as $actor => $reputation) {
            $code = substr(strtoupper(trim((string) $actor)), 0, 32);
            if ($code !== '') {
                $result['reputationByActor'][$code] = self::enum($reputation, [
                    'fatalna', 'zla', 'podejrzana', 'neutralna', 'dobra', 'znakomita',
                ], 'neutralna');
            }
        }
        return $result;
    }

    public static function normalizeEvents($input): array
    {
        $events = is_array($input) ? array_slice($input, 0, 50) : [];
        $result = [];
        foreach ($events as $index => $event) {
            if (!is_array($event)) {
                continue;
            }
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) ($event['id'] ?? ''));
            $result[] = [
                'id' => substr($id ?: 'market-event-' . ($index + 1), 0, 80),
                'name' => substr(trim((string) ($event['name'] ?? '')), 0, 120),
                'type' => self::enum($event['type'] ?? 'custom', [
                    'war', 'plague', 'siege', 'festival', 'crop_failure', 'closed_route', 'custom',
                ], 'custom'),
                'enabled' => ($event['enabled'] ?? true) !== false,
                'startsAt' => self::date($event['startsAt'] ?? null),
                'endsAt' => self::date($event['endsAt'] ?? null),
                'multiplier' => self::number($event['multiplier'] ?? 1, 0.1, 5),
                'availabilityDelta' => self::number($event['availabilityDelta'] ?? 0, -100, 100),
                'modes' => self::allowedList($event['modes'] ?? ['buy', 'sell'], ['buy', 'sell']),
                'itemClasses' => self::upperList($event['itemClasses'] ?? [], 30),
                'itemGenres' => self::upperList($event['itemGenres'] ?? [], 30),
                'locationTypes' => self::stringList($event['locationTypes'] ?? [], 30, 64),
                'templateIds' => self::positiveIntList($event['templateIds'] ?? [], 100),
            ];
        }
        return $result;
    }

    public static function normalizePresets($input): array
    {
        $raw = is_array($input) ? $input : [];
        return [
            'profiles' => self::presetList($raw['profiles'] ?? [], 'profile'),
            'policies' => self::presetList($raw['policies'] ?? [], 'policy'),
        ];
    }

    public static function validate(array $payload): array
    {
        $errors = [];
        if (array_key_exists('counterfeitRisk', $payload)) {
            if (!is_numeric($payload['counterfeitRisk'])) {
                $errors['counterfeitRisk'] = 'must_be_numeric';
            } elseif ((float) $payload['counterfeitRisk'] < 0 || (float) $payload['counterfeitRisk'] > 100) {
                $errors['counterfeitRisk'] = 'outside_range';
            }
        }
        $enums = [
            'legalStatus' => ['legal', 'licensed', 'mixed', 'grey', 'illegal'],
            'wealthTier' => ['nedzny', 'biedny', 'standard', 'bogaty', 'elitarny', 'luksusowy'],
            'reputation' => ['fatalna', 'zla', 'podejrzana', 'neutralna', 'dobra', 'znakomita'],
            'seasonality' => ['caloroczny', 'sezonowy', 'wiosna', 'lato', 'jesien', 'zima', 'zniwa', 'jarmark', 'swieta'],
        ];
        foreach ($enums as $key => $allowed) {
            if (isset($payload[$key]) && !in_array(strtolower((string) $payload[$key]), $allowed, true)) {
                $errors[$key] = 'unsupported_value';
            }
        }
        $settings = is_array($payload['marketSettings'] ?? null) ? $payload['marketSettings'] : [];
        if (isset($settings['demandLevel']) && !in_array($settings['demandLevel'], [
            'very_low', 'low', 'normal', 'high', 'extreme',
        ], true)) {
            $errors['marketSettings.demandLevel'] = 'unsupported_value';
        }
        foreach ((array) ($settings['reputationByActor'] ?? []) as $actor => $value) {
            if (!in_array($value, ['fatalna', 'zla', 'podejrzana', 'neutralna', 'dobra', 'znakomita'], true)) {
                $errors['marketSettings.reputationByActor.' . substr((string) $actor, 0, 32)] = 'unsupported_value';
            }
        }
        foreach (['availabilityBias', 'buybackBudget', 'maxBuybackItemValue', 'expensiveStockLimit'] as $key) {
            if (isset($settings[$key]) && $settings[$key] !== '' && !is_numeric($settings[$key])) {
                $errors['marketSettings.' . $key] = 'must_be_numeric';
            }
        }
        if (isset($settings['availabilityBias']) && is_numeric($settings['availabilityBias']) &&
            ((float) $settings['availabilityBias'] < -50 || (float) $settings['availabilityBias'] > 50)) {
            $errors['marketSettings.availabilityBias'] = 'outside_range';
        }
        foreach ((array) ($payload['marketEvents'] ?? []) as $index => $rawEvent) {
            if (!is_array($rawEvent)) {
                $errors['marketEvents.' . $index] = 'must_be_object';
                continue;
            }
            if (trim((string) ($rawEvent['name'] ?? '')) === '') {
                $errors['marketEvents.' . $index . '.name'] = 'required';
            }
            if (isset($rawEvent['type']) && !in_array($rawEvent['type'], [
                'war', 'plague', 'siege', 'festival', 'crop_failure', 'closed_route', 'custom',
            ], true)) {
                $errors['marketEvents.' . $index . '.type'] = 'unsupported_value';
            }
            foreach ((array) ($rawEvent['modes'] ?? []) as $mode) {
                if (!in_array($mode, ['buy', 'sell'], true)) {
                    $errors['marketEvents.' . $index . '.modes'] = 'unsupported_value';
                    break;
                }
            }
            foreach (['startsAt', 'endsAt'] as $dateKey) {
                $value = trim((string) ($rawEvent[$dateKey] ?? ''));
                if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $errors['marketEvents.' . $index . '.' . $dateKey] = 'invalid_date';
                }
            }
            $startsAt = self::date($rawEvent['startsAt'] ?? null);
            $endsAt = self::date($rawEvent['endsAt'] ?? null);
            if ($startsAt && $endsAt && $endsAt < $startsAt) {
                $errors['marketEvents.' . $index . '.endsAt'] = 'before_start';
            }
            foreach (['multiplier' => [0.1, 5], 'availabilityDelta' => [-100, 100]] as $key => $range) {
                $value = $rawEvent[$key] ?? ($key === 'multiplier' ? 1 : 0);
                if (!is_numeric($value)) {
                    $errors['marketEvents.' . $index . '.' . $key] = 'must_be_numeric';
                } elseif ((float) $value < $range[0] || (float) $value > $range[1]) {
                    $errors['marketEvents.' . $index . '.' . $key] = 'outside_range';
                }
            }
        }
        $config = is_array($payload['pricingConfig'] ?? null) ? $payload['pricingConfig'] : [];
        foreach ((array) ($config['baseMultipliers'] ?? []) as $mode => $value) {
            if (!in_array($mode, ['buy', 'sell'], true) || !is_numeric($value) || (float) $value < 0) {
                $errors['pricingConfig.baseMultipliers.' . $mode] = 'invalid_multiplier';
            }
        }
        $guards = (array) ($config['guardrails'] ?? []);
        foreach (['buy', 'sell'] as $mode) {
            $min = $guards[$mode . 'MinMultiplier'] ?? null;
            $max = $guards[$mode . 'MaxMultiplier'] ?? null;
            if ($min !== null && $max !== null && is_numeric($min) && is_numeric($max) && (float) $min > (float) $max) {
                $errors['pricingConfig.guardrails.' . $mode . 'MaxMultiplier'] = 'below_minimum';
            }
        }
        return $errors;
    }

    public static function portableProfile(array $profile): array
    {
        $settings = self::normalizeSettings($profile['marketSettings'] ?? []);
        $settings['reputationByActor'] = [];
        $events = self::normalizeEvents($profile['marketEvents'] ?? []);
        foreach ($events as &$event) {
            $event['templateIds'] = [];
        }
        unset($event);
        $presets = self::normalizePresets($profile['customPresets'] ?? []);
        foreach ($presets['profiles'] as &$preset) {
            $values = array_intersect_key((array) $preset['values'], array_flip([
                'typeId', 'worldProfileId', 'locationType', 'legalStatus', 'wealthTier',
                'reputation', 'seasonality', 'counterfeitRisk', 'marketSettings', 'marketEvents',
            ]));
            if (isset($values['marketSettings'])) {
                $values['marketSettings'] = self::normalizeSettings($values['marketSettings']);
                $values['marketSettings']['reputationByActor'] = [];
            }
            if (isset($values['marketEvents'])) {
                $values['marketEvents'] = self::normalizeEvents($values['marketEvents']);
                foreach ($values['marketEvents'] as &$presetEvent) $presetEvent['templateIds'] = [];
                unset($presetEvent);
            }
            $preset['values'] = $values;
        }
        unset($preset);
        foreach ($presets['policies'] as &$preset) {
            $preset['values'] = ShopPricingService::normalizePricingConfig($preset['values']);
        }
        unset($preset);
        return [
            'schema' => 'blatyrpg.shop-profile',
            'version' => 1,
            'profile' => array_intersect_key($profile, array_flip([
                'typeId', 'worldProfileId', 'locationType', 'legalStatus', 'wealthTier',
                'reputation', 'seasonality', 'counterfeitRisk', 'pricingConfig',
            ])),
            'marketSettings' => $settings,
            'marketEvents' => $events,
            'customPresets' => $presets,
        ];
    }

    private static function presetList($input, string $kind): array
    {
        $result = [];
        foreach (is_array($input) ? array_slice($input, 0, 30) : [] as $index => $preset) {
            if (!is_array($preset)) continue;
            $name = substr(trim((string) ($preset['name'] ?? '')), 0, 80);
            if ($name === '') continue;
            $result[] = [
                'id' => substr((string) ($preset['id'] ?? ($kind . '-' . ($index + 1))), 0, 80),
                'name' => $name,
                'values' => is_array($preset['values'] ?? null) ? $preset['values'] : [],
            ];
        }
        return $result;
    }

    private static function number($value, float $min, float $max): float
    {
        return max($min, min($max, is_numeric($value) ? (float) $value : 0));
    }

    private static function enum($value, array $allowed, string $fallback): string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private static function date($value): ?string
    {
        $value = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private static function stringList($input, int $limit, int $length): array
    {
        $values = is_array($input) ? $input : [];
        return array_values(array_unique(array_filter(array_map(static function ($value) use ($length): string {
            return substr(strtolower(trim((string) $value)), 0, $length);
        }, array_slice($values, 0, $limit)))));
    }

    private static function upperList($input, int $limit): array
    {
        return array_map('strtoupper', self::stringList($input, $limit, 64));
    }

    private static function allowedList($input, array $allowed): array
    {
        $values = self::stringList(is_array($input) ? $input : [$input], 10, 32);
        $result = array_values(array_intersect($values, $allowed));
        return $result ?: $allowed;
    }

    private static function positiveIntList($input, int $limit): array
    {
        $result = [];
        foreach (is_array($input) ? array_slice($input, 0, $limit) : [] as $value) {
            $id = (int) $value;
            if ($id > 0) $result[$id] = $id;
        }
        return array_values($result);
    }
}
