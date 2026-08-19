<?php

namespace App\Services\Shop;

final class ShopItemMechanicsService
{
    private const TRIGGERS = ['USE', 'ATTACK', 'CONSUME', 'EQUIP', 'PASSIVE', 'CUSTOM'];
    private const HANDLERS = ['GENERIC', 'DICE_TEST', 'ATTACK', 'CONSUME', 'APPLY_EFFECTS', 'CUSTOM'];
    private const CONDITIONS = ['ALWAYS', 'SUCCESS', 'FAILURE'];
    private const EFFECT_TYPES = ['DAMAGE', 'HEAL', 'STATUS', 'MODIFIER', 'RESOURCE', 'CUSTOM'];
    private const TARGETS = ['SELF', 'TARGET', 'AREA'];
    private const MODES = ['INHERIT', 'EXTEND', 'REPLACE'];

    public function normalizeMode($value): string
    {
        $mode = strtoupper(trim((string) $value));
        return in_array($mode, self::MODES, true) ? $mode : 'EXTEND';
    }

    public function normalizeMechanics($value): array
    {
        $result = [];
        foreach (array_slice((array) $value, 0, 50) as $index => $mechanic) {
            if (!is_array($mechanic)) {
                continue;
            }
            $normalized = $this->normalizeMechanic($mechanic, $index);
            $result[$normalized['code']] = $normalized;
        }
        return array_values($result);
    }

    public function resolve(
        $classMechanics,
        $genreMechanics,
        $templateMechanics,
        $mode = 'EXTEND'
    ): array {
        $resolved = [];
        $merge = function ($values, string $source) use (&$resolved): void {
            foreach ($this->normalizeMechanics($values) as $mechanic) {
                $code = $mechanic['code'];
                if (!$mechanic['enabled']) {
                    unset($resolved[$code]);
                    continue;
                }
                $mechanic['source'] = $source;
                $resolved[$code] = $mechanic;
            }
        };

        $normalizedMode = $this->normalizeMode($mode);
        if ($normalizedMode !== 'REPLACE') {
            $merge($classMechanics, 'CLASS');
            $merge($genreMechanics, 'GENRE');
        }
        if ($normalizedMode !== 'INHERIT') {
            $merge($templateMechanics, 'TEMPLATE');
        }

        return array_values($resolved);
    }

    private function normalizeMechanic(array $input, int $index): array
    {
        $fallbackCode = 'MECHANIC_'.($index + 1);
        $code = $this->code($input['code'] ?? $fallbackCode, $fallbackCode);
        $trigger = $this->enum($input['trigger'] ?? 'USE', self::TRIGGERS, 'USE');
        $handler = $this->enum($input['handler'] ?? 'GENERIC', self::HANDLERS, 'GENERIC');
        $checkInput = is_array($input['check'] ?? null) ? $input['check'] : [];
        $costInput = is_array($input['cost'] ?? null) ? $input['cost'] : [];

        return [
            'code' => $code,
            'labelPl' => $this->text($input['labelPl'] ?? $code, 160),
            'labelEn' => $this->text($input['labelEn'] ?? $code, 160),
            'enabled' => (bool) ($input['enabled'] ?? true),
            'trigger' => $trigger,
            'handler' => $handler,
            'actionLabel' => $this->text($input['actionLabel'] ?? '', 120),
            'description' => $this->text($input['description'] ?? '', 1000),
            'check' => [
                'enabled' => (bool) ($checkInput['enabled'] ?? false),
                'formula' => $this->text($checkInput['formula'] ?? '1d100', 120),
                'targetKey' => $this->code($checkInput['targetKey'] ?? '', ''),
                'difficulty' => max(-100, min(100, (int) ($checkInput['difficulty'] ?? 0))),
                'comparison' => $this->enum(
                    $checkInput['comparison'] ?? 'LTE',
                    ['LTE', 'LT', 'GTE', 'GT', 'ROLL_ONLY'],
                    'LTE'
                ),
            ],
            'effects' => $this->normalizeEffects($input['effects'] ?? []),
            'cost' => [
                'quantity' => max(0, min(999, (int) ($costInput['quantity'] ?? 0))),
                'charges' => max(0, min(999, (int) ($costInput['charges'] ?? 0))),
                'resourceCode' => $this->code($costInput['resourceCode'] ?? '', ''),
                'resourceValue' => $this->text($costInput['resourceValue'] ?? '', 120),
            ],
            'handlerKey' => $this->text($input['handlerKey'] ?? '', 160),
            'parameters' => is_array($input['parameters'] ?? null)
                ? $input['parameters']
                : [],
        ];
    }

    private function normalizeEffects($value): array
    {
        $effects = [];
        foreach (array_slice((array) $value, 0, 20) as $effect) {
            if (!is_array($effect)) {
                continue;
            }
            $effects[] = [
                'when' => $this->enum($effect['when'] ?? 'ALWAYS', self::CONDITIONS, 'ALWAYS'),
                'type' => $this->enum($effect['type'] ?? 'CUSTOM', self::EFFECT_TYPES, 'CUSTOM'),
                'target' => $this->enum($effect['target'] ?? 'SELF', self::TARGETS, 'SELF'),
                'value' => $this->text($effect['value'] ?? '', 160),
                'duration' => $this->text($effect['duration'] ?? '', 120),
                'resourceCode' => $this->code($effect['resourceCode'] ?? '', ''),
                'statusCode' => $this->code($effect['statusCode'] ?? '', ''),
                'description' => $this->text($effect['description'] ?? '', 500),
            ];
        }
        return $effects;
    }

    private function enum($value, array $allowed, string $fallback): string
    {
        $normalized = strtoupper(trim((string) $value));
        return in_array($normalized, $allowed, true) ? $normalized : $fallback;
    }

    private function code($value, string $fallback): string
    {
        $normalized = strtoupper(trim((string) $value));
        return preg_match('/^[A-Z][A-Z0-9_]{0,63}$/', $normalized)
            ? $normalized
            : $fallback;
    }

    private function text($value, int $maxLength): string
    {
        return mb_substr(trim((string) $value), 0, $maxLength);
    }
}
