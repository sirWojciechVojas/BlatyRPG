<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class A_MasterSeeder extends Seeder
{
    public function run()
    {
        $this->call('RpgInitializationSeeder');
        $this->call('LocalizationSeeder');
        $this->call('WarhammerDefinitionsSeeder');
        $this->call('WarhammerEquipmentSeeder');
        $this->call('WarhammerCombatSeeder');
        $this->call('WarhammerInsanitySeeder');
        $this->call('ProfessionsLegacySeeder');
        $this->call('CharacterLegacySeeder');
        $this->call('CharacterProfessionsLegacySeeder');
    }
}
