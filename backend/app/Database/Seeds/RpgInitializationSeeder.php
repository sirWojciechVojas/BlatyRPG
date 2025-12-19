<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RpgInitializationSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();
                
        // 1. CZYSZCZENIE TABEL (OBOWIĄZKOWE PRZY ZMIANACH STRUKTURY DANYCH)
        // Wyłączamy sprawdzanie kluczy obcych, czyścimy tabele i włączamy z powrotem.
        $db->query("SET FOREIGN_KEY_CHECKS = 0");
        $db->table('rpg_system_universes')->truncate();
        $db->table('game_definitions')->truncate(); // Czyścimy też definicje, bo ID systemów się zmienią!
        $db->table('rpg_systems')->truncate();
        $db->table('rpg_universes')->truncate();
        $db->query("SET FOREIGN_KEY_CHECKS = 1");

        echo "🚀 Inicjalizacja Pełnego Katalogu RPG (Polskie i Światowe)...\n";

        // ==========================================
        // 1. SYSTEMY (Mechanika / Silnik)
        // ==========================================
        $systems = [
            // --- GŁÓWNE (AKTYWNE) ---
            ['code' => 'wfrp2ed', 'name' => 'Warhammer Fantasy Roleplay 2. edycja'],
            ['code' => 'wfrp4e',  'name' => 'Warhammer Fantasy Roleplay 4. edycja'],
            ['code' => 'coc7e',   'name' => 'Zew Cthulhu 7. edycja (Call of Cthulhu)'],

            // --- DUNGEONS & DRAGONS FAMILY ---
            ['code' => 'dnd5e', 'name' => 'Dungeons & Dragons 5. edycja'],
            ['code' => 'pf1e',  'name' => 'Pathfinder 1. edycja'],
            ['code' => 'pf2e',  'name' => 'Pathfinder 2. edycja'],
            ['code' => 'ose',   'name' => 'Old School Essentials (OSE)'],

            // --- WORLD OF DARKNESS (STORYTELLER / STORYPATH) ---
            ['code' => 'v5',          'name' => 'Wampir: Maskarada 5. edycja'],
            ['code' => 'v_dark_ages', 'name' => 'Vampire: The Dark Ages'],
            ['code' => 'wod_core',    'name' => 'World of Darkness (New WoD / CoD)'],
            ['code' => 'mage_asc',    'name' => 'Mage: The Ascension 20th'],
            ['code' => 'werewolf_apoc', 'name' => 'Werewolf: The Apocalypse 5. edycja'],
            ['code' => 'changeling',  'name' => 'Changeling: The Lost'],
            ['code' => 'hunter',      'name' => 'Hunter: The Vigil'],
            ['code' => 'exalted',     'name' => 'Exalted 3. edycja'],

            // --- FREE LEAGUE (YEAR ZERO ENGINE) ---
            ['code' => 'yze_mutant',   'name' => 'Mutant: Year Zero Engine'], 
            ['code' => 'yze_alien',    'name' => 'Alien RPG System'],
            ['code' => 'yze_coriolis', 'name' => 'Coriolis System'],
            ['code' => 'yze_tales',    'name' => 'Tales from the Loop System'],
            ['code' => 'yze_forbidden','name' => 'Forbidden Lands System'],
            ['code' => 'yze_vaesen',   'name' => 'Vaesen System'],
            ['code' => 'yze_twilight', 'name' => 'Twilight: 2000 (4. edycja)'],
            ['code' => 'symbaroum_sys','name' => 'Symbaroum System'],

            // --- BRP / D100 FAMILY ---
            ['code' => 'brp_generic', 'name' => 'Basic RolePlaying (BRP)'],
            ['code' => 'runequest',   'name' => 'RuneQuest'],
            ['code' => 'pendragon',   'name' => 'Pendragon'],
            ['code' => 'delta_green', 'name' => 'Delta Green'],

            // --- POLSKIE SYSTEMY ---
            ['code' => 'neuroshima_sys', 'name' => 'Mechanika Neuroshimie (3k20)'],
            ['code' => 'monastyr_sys',   'name' => 'Mechanika Monastyru (3k20)'],
            ['code' => 'dzikie_pola',    'name' => 'Dzikie Pola (System)'],
            ['code' => 'wolsung_sys',    'name' => 'Wolsung (2k10)'],
            ['code' => 'krysztyly_czasu','name' => 'Kryształy Czasu (k100/k50/k10)'],
            ['code' => 'wiedzon',        'name' => 'Wiedźmin Gra Fabularna (Interlock)'],

            // --- PBTA / FITD (NARRACYJNE) ---
            ['code' => 'pbta_generic',   'name' => 'Powered by the Apocalypse (PbtA)'],
            ['code' => 'dungeon_world',  'name' => 'Dungeon World'],
            ['code' => 'blades_dark',    'name' => 'Blades in the Dark (FitD)'],
            ['code' => 'scum_villainy',  'name' => 'Scum and Villainy'],
            ['code' => 'avatar_legends', 'name' => 'Avatar Legends'],

            // --- 2D20 (MODIPHIUS) ---
            ['code' => 'conan_2d20',     'name' => 'Conan 2d20'],
            ['code' => 'dune_2d20',      'name' => 'Dune 2d20'],
            ['code' => 'startrek_2d20',  'name' => 'Star Trek Adventures'],

            // --- SAVAGE WORLDS / INNE UNIWERSALNE ---
            ['code' => 'swade',          'name' => 'Savage Worlds Adventure Edition (SWADE)'],
            ['code' => 'deadlands_classic','name' => 'Deadlands Classic'],
            ['code' => 'gurps',          'name' => 'GURPS 4th Edition'],
            ['code' => 'fate_core',      'name' => 'Fate Core'],
            ['code' => 'fate_acc',       'name' => 'Fate Accelerated'],
            ['code' => 'cypher',         'name' => 'Cypher System'],

            // --- SPECIFICZNE ---
            ['code' => 'cyberpunk_red',  'name' => 'Cyberpunk RED'],
            ['code' => 'shadowrun5',     'name' => 'Shadowrun 5. edycja'],
            ['code' => 'l5r5e',          'name' => 'Legenda Pięciu Kręgów 5. edycja'],
            ['code' => 'one_ring_2e',    'name' => 'The One Ring 2. edycja (Jedyny Pierścień)'],
            ['code' => 'earthdawn',      'name' => 'Earthdawn (Przebudzenie Ziemi)'],
            ['code' => '7th_sea',        'name' => '7th Sea (2. edycja)'],
            ['code' => 'kult_dl',        'name' => 'Kult: Divinity Lost'],
            ['code' => 'dark_eye',       'name' => 'The Dark Eye (Oko Ciemności)'],
            ['code' => 'ars_magica',     'name' => 'Ars Magica'],
            ['code' => 'numenera',       'name' => 'Numenera'],
            ['code' => 'traveller',      'name' => 'Traveller'],
            ['code' => 'paranoia',       'name' => 'Paranoia'],
            ['code' => 'mouse_guard',    'name' => 'Mouse Guard (Mysia Straż)'],
            ['code' => 'burning_wheel',  'name' => 'The Burning Wheel'],
            ['code' => 'star_wars_ffg',  'name' => 'Star Wars (FFG/Edge - EotE, AoR, FaD)'],
            ['code' => 'game_of_thrones','name' => 'Pieśń Lodu i Ognia RPG (SIFRP)'],
            ['code' => 'dragon_age',     'name' => 'Dragon Age RPG (AGE System)'],
            ['code' => 'expanse',        'name' => 'The Expanse RPG (AGE System)'],
            ['code' => 'wh40k_d100',     'name' => 'Warhammer 40k d100 (DH, RT, DW, OW)'],
            ['code' => 'wh40k_wg',       'name' => 'Warhammer 40k: Wrath & Glory'],
        ];

        // ==========================================
        // 2. UNIWERSA (Setting / Świat)
        // ==========================================
        $universes = [
            // Warhammer
            ['code' => 'old_world',      'name' => 'Stary Świat (The Old World)', 'desc' => 'Ponury świat niebezpiecznych przygód, Imperium, Chaos.'],
            ['code' => 'wh40k_galaxy',   'name' => 'Galaktyka 41. Milenium', 'desc' => 'W mrocznej przyszłości istnieje tylko wojna. Imperium Człowieka vs Xenos.'],
            
            // Cthulhu / Horror
            ['code' => 'lovecraft_1920', 'name' => 'Ziemia (Lata 20. XX wieku)', 'desc' => 'Klasyczny setting Zewu Cthulhu. Jazz, prohibicja i Przedwieczni.'],
            ['code' => 'lovecraft_now',  'name' => 'Ziemia (Współczesność - Delta Green)', 'desc' => 'Rządowe konspiracje i walka z Mitami we współczesności.'],
            ['code' => 'pulp_cthulhu',   'name' => 'Pulp Cthulhu (Lata 30.)', 'desc' => 'Bohaterowie kontra mity, więcej akcji, mniej szaleństwa.'],
            ['code' => 'kult_metropolis','name' => 'Iluzja i Metropolis', 'desc' => 'Gnostycki horror, gdzie rzeczywistość jest więzieniem.'],

            // D&D / Fantasy
            ['code' => 'forgotten_realms','name' => 'Forgotten Realms (Zapomniane Krainy)', 'desc' => 'Faerûn, Wybrzeże Mieczy - najbardziej znany świat D&D.'],
            ['code' => 'golarion',       'name' => 'Golarion', 'desc' => 'Bogaty i różnorodny świat systemu Pathfinder.'],
            ['code' => 'middle_earth',   'name' => 'Śródziemie (Middle-earth)', 'desc' => 'Świat J.R.R. Tolkiena. Shire, Rivendell, Mordor.'],
            ['code' => 'wiedxmin_world', 'name' => 'Kontynent (Świat Wiedźmina)', 'desc' => 'Mroczne fantasy Andrzeja Sapkowskiego. Potwory, wojna i rasizm.'],
            ['code' => 'rokugan',        'name' => 'Rokugan (Szmaragdowe Cesarstwo)', 'desc' => 'Honor, samuraje i magia w świecie inspirowanym Japonią.'],
            ['code' => 'symbaroum_world','name' => 'Ambria i Davokar', 'desc' => 'Mroczny las pełen tajemnic i korupcji.'],
            ['code' => 'earthdawn_world','name' => 'Barsawia (Earthdawn)', 'desc' => 'Świat budzący się po magicznej apokalipsie Horrorów.'],
            ['code' => 'theah',          'name' => 'Théa', 'desc' => 'Świat płaszcza i szpady, piratów i magii.'],
            ['code' => 'conan_world',    'name' => 'Era Hyboryjska', 'desc' => 'Barbarzyński świat Conana.'],
            ['code' => 'dragon_age_world','name' => 'Thedas', 'desc' => 'Świat Dragon Age, Plaga i Szarzy Strażnicy.'],
            ['code' => 'westeros',       'name' => 'Westeros i Essos', 'desc' => 'Świat Gry o Tron.'],

            // Polskie
            ['code' => 'post_apoc_usa',  'name' => 'Zrujnowane USA (Neuroshima)', 'desc' => 'Postapokalipsa, Moloch, Posterunek i mutanty.'],
            ['code' => 'commonwealth',   'name' => 'Rzeczpospolita Obojga Narodów', 'desc' => 'Szlachecki honor, szable i warcholstwo w XVII wieku.'],
            ['code' => 'dominium',       'name' => 'Dominium (Monastyr)', 'desc' => 'Dark fantasy płaszcza i szpady w świecie rządzonym przez Kościół.'],
            ['code' => 'wolsung_world',  'name' => 'Magiczny Wiek Pary (Wolsung)', 'desc' => 'Steampunkowe fantasy, smoki w cylindrach i magia przemysłowa.'],
            ['code' => 'orchia',         'name' => 'Archipelag Orchii', 'desc' => 'Unikalny świat Kryształów Czasu Artura Szyndlera.'],

            // Sci-Fi / Cyberpunk
            ['code' => 'night_city',     'name' => 'Night City (Cyberpunk)', 'desc' => 'Dystopijna przyszłość rządzona przez korporacje.'],
            ['code' => 'sixth_world',    'name' => 'Szósty Świat (Shadowrun)', 'desc' => 'Przyszłość, gdzie magia powróciła. Elfy hakerzy i smoki korporacyjne.'],
            ['code' => 'alien_uni',      'name' => 'Wszechświat Obcego', 'desc' => 'W kosmosie nikt nie usłyszy twojego krzyku. Korporacja Weyland-Yutani.'],
            ['code' => 'star_wars_gal',  'name' => 'Odległa Galaktyka (Star Wars)', 'desc' => 'Jedi, Sithowie, Imperium i Rebelia.'],
            ['code' => 'expanse_sys',    'name' => 'Układ Słoneczny (The Expanse)', 'desc' => 'Realistyczne Sci-Fi, polityka Ziemi, Marsa i Pasa.'],
            ['code' => 'coriolis_3h',    'name' => 'Trzeci Horyzont', 'desc' => 'Arabskie noce w kosmosie.'],
            ['code' => 'numenera_world', 'name' => 'Dziewiąty Świat', 'desc' => 'Ziemia za miliard lat, technologia nierozróżnialna od magii.'],
            ['code' => 'tales_loop_80s', 'name' => 'Lata 80. z Pętlą', 'desc' => 'Alternatywne lata 80., roboty i tajemnice.'],
            ['code' => 'twilight_world', 'name' => 'Polska/Szwecja rok 2000', 'desc' => 'III Wojna Światowa i przetrwanie.'],
            ['code' => 'dune_universe',  'name' => 'Imperium (Diuna)', 'desc' => 'Arrakis, przyprawa i intrygi wielkich rodów.'],
            ['code' => 'federation',     'name' => 'Zjednoczona Federacja Planet', 'desc' => 'Eksploracja kosmosu w świecie Star Trek.'],
            ['code' => 'traveller_uni',  'name' => 'Trzecie Imperium (Traveller)', 'desc' => 'Klasyczna space opera i handel międzygwiezdny.'],
            ['code' => 'paranoia_complex','name' => 'Kompleks Alpha', 'desc' => 'Komputer jest twoim przyjacielem. Zdrada karana jest śmiercią.'],

            // World of Darkness
            ['code' => 'wod_modern',     'name' => 'Świat Mroku (Gotycki Punk)', 'desc' => 'Nasz świat, ale mroczniejszy. Wampiry, Wilkołaki, Magowie ukryci w cieniu.'],
            ['code' => 'wod_medieval',   'name' => 'Mroczne Wieki', 'desc' => 'Średniowieczna wersja Świata Mroku.'],

            // Inne
            ['code' => 'weird_west',     'name' => 'Dziwny Zachód (Deadlands)', 'desc' => 'Western z elementami horroru i steampunku.'],
            ['code' => 'mythic_europe',  'name' => 'Mityczna Europa', 'desc' => 'Historyczne średniowiecze, ale magia jest prawdziwa (Ars Magica).'],
            ['code' => 'doskvol',        'name' => 'Doskvol (Blades in the Dark)', 'desc' => 'Industrialne miasto wiecznej nocy napędzane ektoplazmą.'],
            ['code' => 'apocalypse_world','name' => 'Apocalypse World', 'desc' => 'Brudna, brutalna postapokalipsa.'],
            ['code' => 'dungeon_world',  'name' => 'Dungeon World (Fantasy)', 'desc' => 'Klasyczne fantasy w stylu D&D, ale na mechanice PbtA.'],
            ['code' => 'four_nations',   'name' => 'Cztery Narody (Avatar)', 'desc' => 'Świat Avatara, magii żywiołów.'],
            ['code' => 'glorantha',      'name' => 'Glorantha', 'desc' => 'Mityczny świat brązu, bogów i bohaterów.'],
        ];

        // Import do bazy (teraz do pustych tabel, więc ID będą szły od 1)
        $sysMap = [];
        foreach ($systems as $s) {
            // Bezpośredni insert, bo zrobiliśmy truncate
            $this->db->table('rpg_systems')->insert([
                'code' => $s['code'],
                'name' => $s['name'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $sysMap[$s['code']] = $this->db->insertID();
        }

        $uniMap = [];
        foreach ($universes as $u) {
            $this->db->table('rpg_universes')->insert([
                'code' => $u['code'],
                'name' => $u['name'],
                'description' => $u['desc'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $uniMap[$u['code']] = $this->db->insertID();
        }

        // ==========================================
        // 3. PAROWANIE (Tabela Pivot)
        // ==========================================
        
        $games = [
            // --- GRY AKTYWNE (PRIORYTET) ---
            ['wfrp2ed', 'old_world', true], // WFRP 2ed
            ['wfrp4e',  'old_world', true], // WFRP 4ed
            ['coc7e',   'lovecraft_1920', true], // Zew Cthulhu 7ed

            // --- POLSKIE KLASYKI ---
            ['neuroshima_sys', 'post_apoc_usa', false],
            ['monastyr_sys', 'dominium', false],
            ['dzikie_pola', 'commonwealth', false],
            ['wolsung_sys', 'wolsung_world', false],
            ['krysztyly_czasu', 'orchia', false],
            ['wiedzon', 'wiedxmin_world', false],

            // --- DUNGEONS & DRAGONS & PATHFINDER ---
            ['dnd5e', 'forgotten_realms', false],
            ['dnd5e', 'ravenloft', false], 
            ['dnd5e', 'middle_earth', false],
            ['pf1e',  'golarion', false],
            ['pf2e',  'golarion', false],
            ['ose',   'forgotten_realms', false], 

            // --- WORLD OF DARKNESS ---
            ['v5',            'wod_modern', false],
            ['v_dark_ages',   'wod_medieval', false],
            ['werewolf_apoc', 'wod_modern', false],
            ['mage_asc',      'wod_modern', false],
            ['changeling',    'wod_modern', false],
            ['hunter',        'wod_modern', false],
            ['wod_core',      'wod_modern', false], 

            // --- CYBERPUNK & SCI-FI ---
            ['cyberpunk_red', 'night_city', false],
            ['shadowrun5',    'sixth_world', false],
            ['yze_alien',     'alien_uni', false], // Alien RPG
            ['traveller',     'traveller_uni', false],
            ['startrek_2d20', 'federation', false],
            ['expanse',       'expanse_sys', false],
            ['star_wars_ffg', 'star_wars_gal', false],
            ['paranoia',      'paranoia_complex', false],

            // --- YEAR ZERO ENGINE (FREE LEAGUE) ---
            ['yze_mutant',    'post_apoc_usa', false], 
            ['yze_coriolis',  'coriolis_3h', false],
            ['yze_tales',     'tales_loop_80s', false],
            ['yze_vaesen',    'mythic_europe', false], 
            ['yze_twilight',  'twilight_world', false],
            ['yze_forbidden', 'symbaroum_world', false], 
            ['symbaroum_sys', 'symbaroum_world', false],

            // --- INNE HITY ---
            ['l5r5e',         'rokugan', false],
            ['one_ring_2e',   'middle_earth', false],
            ['savage_worlds', 'weird_west', false], 
            ['deadlands_classic', 'weird_west', false],
            ['earthdawn',     'earthdawn_world', false],
            ['7th_sea',       'theah', false],
            ['conan_2d20',    'conan_world', false],
            ['dune_2d20',     'dune_universe', false],
            ['kult_dl',       'kult_metropolis', false],
            ['dark_eye',      'mythic_europe', false], 
            ['ars_magica',    'mythic_europe', false],
            ['numenera',      'numenera_world', false],
            ['brp_generic',   'lovecraft_1920', false], 
            ['runequest',     'glorantha', false],
            ['delta_green',   'lovecraft_now', false],
            
            // --- WARHAMMER 40K ---
            ['wh40k_d100',    'wh40k_galaxy', false], 
            ['wh40k_wg',      'wh40k_galaxy', false], 

            // --- PBTA / NARRACYJNE ---
            ['blades_dark',   'doskvol', false],
            ['scum_villainy', 'star_wars_gal', false], 
            ['dungeon_world', 'dungeon_world', false],
            ['fate_core',     'wolsung_world', false], 
            ['mouse_guard',   'middle_earth', false], 
            ['burning_wheel', 'middle_earth', false], 
            ['avatar_legends', 'four_nations', false],
        ];

        // Wstawianie do Pivot
        $pivotBuilder = $db->table('rpg_system_universes');
        $count = 0;

        foreach ($games as $game) {
            $sysCode = $game[0];
            $uniCode = $game[1];
            $isActive = $game[2];

            if (isset($sysMap[$sysCode]) && isset($uniMap[$uniCode])) {
                $pivotBuilder->insert([
                    'system_id'   => $sysMap[$sysCode],
                    'universe_id' => $uniMap[$uniCode],
                    'is_active'   => $isActive ? 1 : 0
                ]);
                $count++;
            }
        }

        echo "✅ Zakończono. Skonfigurowano $count gier.\n";
    }
}