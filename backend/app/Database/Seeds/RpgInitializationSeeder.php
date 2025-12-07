<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RpgInitializationSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();
        $sysBuilder = $db->table('rpg_systems');
        $uniBuilder = $db->table('rpg_universes');

        echo "🌍 Inicjalizacja Top 20 Systemów i Uniwersów RPG...\n";

        // Lista Top 20 gier (System + Domyślne Uniwersum)
        $topGames = [
            // D&D i pochodne
            [
                'sys_code' => 'dnd5e', 'sys_name' => 'Dungeons & Dragons 5th Edition',
                'uni_code' => 'forgotten_realms', 'uni_name' => 'Forgotten Realms (Zapomniane Krainy)',
                'uni_desc' => 'High fantasy, magia, smoki i epickie przygody.'
            ],
            [
                'sys_code' => 'pathfinder2e', 'sys_name' => 'Pathfinder 2nd Edition',
                'uni_code' => 'golarion', 'uni_name' => 'Golarion (Age of Lost Omens)',
                'uni_desc' => 'Bogaty świat fantasy pełen starożytnych tajemnic i politycznych intryg.'
            ],
            // Horror / Investigation
            [
                'sys_code' => 'coc7e', 'sys_name' => 'Call of Cthulhu 7th Edition',
                'uni_code' => 'lovecraft_mythos', 'uni_name' => 'Lovecraft Country / Nasz Świat',
                'uni_desc' => 'Lata 20. XX wieku, kosmiczny horror i przedwieczni bogowie.'
            ],
            [
                'sys_code' => 'delta_green', 'sys_name' => 'Delta Green',
                'uni_code' => 'modern_conspiracy', 'uni_name' => 'Współczesna Ziemia (Konspiracja)',
                'uni_desc' => 'Agenci rządowi walczący z nienazwanym złem.'
            ],
            [
                'sys_code' => 'alien_rpg', 'sys_name' => 'Alien: The Roleplaying Game',
                'uni_code' => 'alien_universe', 'uni_name' => 'Alien Universe (2183)',
                'uni_desc' => 'Space horror w korporacyjnej dystopii.'
            ],
            [
                'sys_code' => 'motw', 'sys_name' => 'Monster of the Week',
                'uni_code' => 'modern_monster_hunting', 'uni_name' => 'Współczesność (Polowanie na potwory)',
                'uni_desc' => 'Klimaty seriali Supernatural czy Buffy: Postrach Wampirów.'
            ],
            // Warhammer
            [
                'sys_code' => 'wfrp4e', 'sys_name' => 'Warhammer Fantasy Roleplay 4th Ed',
                'uni_code' => 'old_world', 'uni_name' => 'Stary Świat (The Old World)',
                'uni_desc' => 'Mroczne fantasy, Chaos i niebezpieczeństwo za każdym rogiem.'
            ],
            [
                'sys_code' => 'wrath_glory', 'sys_name' => 'Warhammer 40,000: Wrath & Glory',
                'uni_code' => 'wh40k', 'uni_name' => 'Warhammer 40k Universe',
                'uni_desc' => 'Grim dark future, w którym istnieje tylko wojna.'
            ],
            // Cyberpunk / Sci-Fi
            [
                'sys_code' => 'cyberpunk_red', 'sys_name' => 'Cyberpunk RED',
                'uni_code' => 'night_city', 'uni_name' => 'Night City (Czas Czerwieni)',
                'uni_desc' => 'Mroczna przyszłość rządzona przez megakorporacje.'
            ],
            [
                'sys_code' => 'shadowrun6e', 'sys_name' => 'Shadowrun 6th World',
                'uni_code' => 'sixth_world', 'uni_name' => 'Sixth World (Magia + Technologia)',
                'uni_desc' => 'Cyberpunk połączony z fantasy. Orki hakerzy i smoki korporacyjne.'
            ],
            [
                'sys_code' => 'traveller_mg2', 'sys_name' => 'Traveller (Mongoose 2e)',
                'uni_code' => 'third_imperium', 'uni_name' => 'Third Imperium',
                'uni_desc' => 'Klasyczna opera kosmiczna i eksploracja.'
            ],
            [
                'sys_code' => 'dune_2d20', 'sys_name' => 'Dune: Adventures in the Imperium',
                'uni_code' => 'dune_arrakis', 'uni_name' => 'Imperium (Diuna)',
                'uni_desc' => 'Polityka, intrygi i przyprawa na pustynnej planecie.'
            ],
            [
                'sys_code' => 'startrek_adv', 'sys_name' => 'Star Trek Adventures',
                'uni_code' => 'federation', 'uni_name' => 'Zjednoczona Federacja Planet',
                'uni_desc' => 'Eksploracja kosmosu, dyplomacja i nauka.'
            ],
            // Storyteller / World of Darkness
            [
                'sys_code' => 'v5', 'sys_name' => 'Vampire: The Masquerade 5th Ed',
                'uni_code' => 'wod_vampire', 'uni_name' => 'Świat Mroku (Wampir)',
                'uni_desc' => 'Gotycki horror, osobisty horror i polityka nieumarłych.'
            ],
            // Inne
            [
                'sys_code' => 'l5r5e', 'sys_name' => 'Legend of the Five Rings 5e',
                'uni_code' => 'rokugan', 'uni_name' => 'Szmaragdowe Cesarstwo (Rokugan)',
                'uni_desc' => 'Honor, stal i magia w świecie inspirowanym feudalną Japonią.'
            ],
            [
                'sys_code' => 'blades_dark', 'sys_name' => 'Blades in the Dark',
                'uni_code' => 'doskvol', 'uni_name' => 'Doskvol (Industrial Fantasy)',
                'uni_desc' => 'Złodzieje i gangi w nawiedzonym, industrialnym mieście.'
            ],
            [
                'sys_code' => 'savage_worlds', 'sys_name' => 'Savage Worlds (ADE)',
                'uni_code' => 'deadlands', 'uni_name' => 'Deadlands: Dziwny Zachód',
                'uni_desc' => 'Western z elementami horroru i steampunku (domyślny setting).'
            ],
            [
                'sys_code' => 'one_ring', 'sys_name' => 'The One Ring 2e',
                'uni_code' => 'middle_earth', 'uni_name' => 'Śródziemie (Middle-earth)',
                'uni_desc' => 'Podróże i walka z Cieniem w świecie Tolkiena.'
            ],
            [
                'sys_code' => 'avatar_legends', 'sys_name' => 'Avatar Legends',
                'uni_code' => 'avatar_world', 'uni_name' => 'Świat Avatara (Cztery Narody)',
                'uni_desc' => 'Równowaga żywiołów, sztuki walki i bohaterstwo.'
            ],
            // Specjalny przypadek: WFRP 2ed (Baza dla drugiego seedera)
            [
                'sys_code' => 'wfrp2ed', 'sys_name' => 'Warhammer Fantasy Roleplay 2ed',
                'uni_code' => 'old_world_classic', 'uni_name' => 'Stary Świat (Klasyczny - Burza Chaosu)',
                'uni_desc' => 'Ponury świat niebezpiecznych przygód (edycja klasyczna).'
            ],
        ];

        foreach ($topGames as $game) {
            // 1. Sprawdź/Dodaj System
            $system = $sysBuilder->where('code', $game['sys_code'])->get()->getRow();
            if (!$system) {
                $sysBuilder->insert([
                    'code' => $game['sys_code'],
                    'name' => $game['sys_name'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $systemId = $db->insertID();
            } else {
                $systemId = $system->id;
            }

            // 2. Sprawdź/Dodaj Uniwersum
            $universe = $uniBuilder->where('code', $game['uni_code'])->get()->getRow();
            if (!$universe) {
                $uniBuilder->insert([
                    'code' => $game['uni_code'],
                    'default_system_id' => $systemId,
                    'name' => $game['uni_name'],
                    'description' => $game['uni_desc'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }
}