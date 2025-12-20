<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class LocalizationSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();
        $table = $db->table('locales');

        $locales = [
            ['code' => 'pl', 'name' => 'Polski', 'is_default' => 1],
            ['code' => 'en', 'name' => 'English', 'is_default' => 0],
            ['code' => 'fr', 'name' => 'Français', 'is_default' => 0],
            ['code' => 'de', 'name' => 'Deutsch', 'is_default' => 0],
        ];

        foreach ($locales as $loc) {
            $exists = $table->where('code', $loc['code'])->countAllResults();
            if ($exists == 0) {
                $table->insert($loc);
            } else {
                $table->where('code', $loc['code'])->update($loc);
            }
        }

        echo "Zainicjowano języki (pl/en/fr/de), domyślny: pl.\n";
    }
}
