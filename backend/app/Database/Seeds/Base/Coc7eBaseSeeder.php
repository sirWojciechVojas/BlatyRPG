<?php

namespace App\Database\Seeds\Base;

use App\Database\Seeds\Data\Coc7e1920sData;

/**
 * Wspolne helpery dla seederow CoC 7e (1920s).
 */
abstract class Coc7eBaseSeeder extends SystemBaseSeeder
{
    protected string $systemCode = 'coc7e';

    protected function ensureCocDataAvailable(): bool
    {
        if (!class_exists(Coc7e1920sData::class)) {
            echo "Brak klasy Coc7e1920sData.\n";
            return false;
        }

        return true;
    }

    protected function getCocRows(string $method, string $missingMessage): array
    {
        if (!method_exists(Coc7e1920sData::class, $method)) {
            echo $missingMessage . "\n";
            return [];
        }

        $rows = call_user_func([Coc7e1920sData::class, $method]);
        return is_array($rows) ? $rows : [];
    }
}
