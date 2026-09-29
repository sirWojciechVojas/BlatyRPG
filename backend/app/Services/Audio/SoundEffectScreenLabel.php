<?php

namespace App\Services\Audio;

use InvalidArgumentException;

/** Spreadsheet-style screen labels: A..Z, AA..AZ, BA... */
final class SoundEffectScreenLabel
{
    public static function fromIndex(int $index): string
    {
        if ($index < 0) {
            throw new InvalidArgumentException('Screen index must not be negative.');
        }
        $label = '';
        for ($value = $index + 1; $value > 0; $value = intdiv($value - 1, 26)) {
            $label = chr(65 + (($value - 1) % 26)) . $label;
        }
        return $label;
    }

    public static function toIndex(string $label): int
    {
        $normalized = strtoupper(trim($label));
        if (!preg_match('/^[A-Z]+$/', $normalized)) {
            throw new InvalidArgumentException('Screen label is invalid.');
        }
        $value = 0;
        foreach (str_split($normalized) as $character) {
            $value = ($value * 26) + ord($character) - 64;
        }
        return $value - 1;
    }
}
