<?php

namespace App\Services\Token;

final class TokenResourceBinding
{
    public static function tokenToActor(
        array $previous,
        array $resources,
        array $characterData
    ): array {
        $previous = TokenResourceValidator::stored($previous);
        $resources = TokenResourceValidator::stored($resources);
        $data = $characterData;
        foreach ($resources['bars'] as $index => &$bar) {
            self::fromToken(
                $bar,
                $previous['bars'][$index],
                'attributePath',
                'value',
                $data
            );
            self::fromToken(
                $bar,
                $previous['bars'][$index],
                'maxAttributePath',
                'max',
                $data
            );
        }
        unset($bar);
        foreach ($resources['bubbles'] as $index => &$bubble) {
            self::fromToken(
                $bubble,
                $previous['bubbles'][$index],
                'attributePath',
                'value',
                $data
            );
        }
        unset($bubble);
        return [
            'resources' => $resources,
            'characterData' => $data,
            'characterChanged' => $data !== $characterData,
        ];
    }

    public static function actorToToken(array $resources, array $characterData): array
    {
        $original = TokenResourceValidator::stored($resources);
        $resources = $original;
        foreach ($resources['bars'] as &$bar) {
            self::fromActor($bar, 'attributePath', 'value', $characterData);
            self::fromActor($bar, 'maxAttributePath', 'max', $characterData);
        }
        unset($bar);
        foreach ($resources['bubbles'] as &$bubble) {
            self::fromActor($bubble, 'attributePath', 'value', $characterData);
        }
        unset($bubble);
        return ['resources' => $resources, 'changed' => $resources !== $original];
    }

    private static function fromToken(
        array &$resource,
        array $previous,
        string $pathField,
        string $valueField,
        array &$data
    ): void {
        $path = (string) ($resource[$pathField] ?? '');
        if ($path === '') return;
        [$exists, $actorValue] = self::read($data, $path);
        $newBinding = $path !== (string) ($previous[$pathField] ?? '');
        if ($newBinding && $exists && is_numeric($actorValue)) {
            $resource[$valueField] = (float) $actorValue;
            return;
        }
        self::write($data, $path, (float) $resource[$valueField]);
    }

    private static function fromActor(
        array &$resource,
        string $pathField,
        string $valueField,
        array $data
    ): void {
        $path = (string) ($resource[$pathField] ?? '');
        if ($path === '') return;
        [$exists, $value] = self::read($data, $path);
        if ($exists && is_numeric($value)) $resource[$valueField] = (float) $value;
    }

    private static function read(array $data, string $path): array
    {
        $value = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return [false, null];
            }
            $value = $value[$segment];
        }
        return [true, $value];
    }

    private static function write(array &$data, string $path, float $value): void
    {
        $cursor = &$data;
        $segments = explode('.', $path);
        $last = array_pop($segments);
        foreach ($segments as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
        $cursor[$last] = $value;
    }
}
