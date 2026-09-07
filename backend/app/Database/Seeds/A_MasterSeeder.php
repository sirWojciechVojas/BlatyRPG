<?php

namespace App\Database\Seeds;

use App\Services\Shop\LegacyCharacterInventoryImporter;
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
        $this->call('ShopTypeSeeder');
        $this->call('ShopModuleSeeder');
        $this->call('CompendiumDemoSeeder');
        (new LegacyCharacterInventoryImporter($this->db))->import();
    }
}
