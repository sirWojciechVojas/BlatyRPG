<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CharacterLegacySeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();
        
        // 1. Sprawdzenie systemu i uniwersum (WFRP 2ed / Old World)
        $systemTable = $db->table('rpg_systems');
        $universeTable = $db->table('rpg_universes');

        $wfrpSystem = $systemTable->where('code', 'wfrp2ed')->get()->getRow();
        if (!$wfrpSystem) {
            $systemTable->insert([
                'code' => 'wfrp2ed',
                'name' => 'WFRP 2ed',
                'description' => 'Warhammer Fantasy Roleplay 2nd Edition',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $wfrpSystem = (object) ['id' => $db->insertID()];
        }

        $oldWorld = $universeTable->where('code', 'old_world')->get()->getRow();
        if (!$oldWorld) {
            $universeTable->insert([
                'code' => 'old_world',
                'name' => 'Old World',
                'description' => 'Warhammer Fantasy setting',
                'default_system_id' => $wfrpSystem->id,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $oldWorld = (object) ['id' => $db->insertID()];
        }

        $sysId = $wfrpSystem->id;
        $uniId = $oldWorld->id;

        echo "🧙‍♂️ Pełna migracja postaci (Statystyki 16 cech + Umiejętności + Zdolności)... \n";

        // ==========================================
        // DANE ZBIORCZE
        // ==========================================
        // Do każdego rekordu dodałem 'skills' i 'talents'
        
        $sourceData = [
            [
                'id' => 1, 'usedname' => 'Enguerrand Rocheteau', 'name' => 'Enguerrand Rocheteau', 'breed' => 'człowiek', 'sex' => 'Mężczyzna', 'age' => 29,
                'height' => 180, 'weight' => 70, 'hair' => 'ciemnobrązowe', 'eyes' => 'jasnoszare', 'sign' => 'Dwa Byki (03)', 'siblings' => '4', 'place' => 'Averland', 'special' => 'znamię',
                'history' => 'Twoja matka zmarła na ciężką chorobę ponad 10 lat temu...',
                'god' => 'Mannan', 'avatar' => 'Enguerrand_Rocheteau_a4x3zf', 'career_id' => 194,
                'stats_cur' => [30,27,35,35,32,28,31,25, 1,11,3,3,4,0, 0,3], 
                'stats_start' => [24,31,31,32,27,22,29,24, 1,11,3,3,4,0, 0,3],
                'stats_adv' => [0,10,10,5,10,5,5,0, 0,2,0,0,0,0, 0,0],
                // NOWE POLA
                'skills' => ['Plotkowanie', 'Przekonywanie', 'Zastraszanie', 'Unik', 'Wiedza (Imperium)', 'Spostrzegawczość', 'Szukanie', 'Ukrywanie się', 'Ciche Chodzenie'],
                'talents' => ['Mocna głowa', 'Błyskawiczny refleks', 'Urodzony wojownik'],
                'meta' => ['Ibra', 'Vojas', 2, 2522]
            ],
            [
                'id' => 2, 'usedname' => 'Leopold Leinweber', 'name' => 'Leopold Leinweber', 'breed' => 'człowiek', 'sex' => 'Mężczyzna', 'age' => 29,
                'height' => 178, 'weight' => 68, 'hair' => 'miedziane', 'eyes' => 'szaroniebieskie', 'sign' => 'Dwa Byki (03)', 'siblings' => '3', 'place' => 'Nuln', 'special' => 'brak',
                'history' => 'Pochodzisz z rodziny kupieckiej z Nuln...',
                'god' => 'Verena', 'avatar' => 'Leopold_Leinweber_kiky43', 'career_id' => 194,
                'stats_cur' => [30,27,35,35,32,28,31,25, 1,10,3,3,4,0, 0,2],
                'stats_start' => [29,32,27,30,34,28,31,27, 1,10,2,3,4,0, 0,2],
                'stats_adv' => [0,10,10,5,10,5,5,0, 0,2,0,0,0,0, 0,0],
                'skills' => ['Czytanie i Pisanie', 'Wiedza (Teologia)', 'Wiedza (Prawo)', 'Język (Klasyczny)', 'Przekonywanie', 'Spostrzegawczość'],
                'talents' => ['Błyskotliwość', 'Charyzmatyczny'],
                'meta' => ['Tutti', 'Vojas', 2, 2522]
            ],
            [
                'id' => 3, 'usedname' => 'Igor z Emmanuelplatz', 'name' => 'Igor z Emmanuelplatz', 'breed' => 'człowiek', 'sex' => 'Mężczyzna', 'age' => 29,
                'height' => 178, 'weight' => 85, 'hair' => 'ciemnorude', 'eyes' => 'piwne', 'sign' => 'Tłusty Kozioł (15)', 'siblings' => 'brak rodzeństwa', 'place' => 'Nuln', 'special' => 'duży nos',
                'history' => 'Od młodych lat żyłeś z wymuszania haraczy...',
                'god' => 'Mannan', 'avatar' => 'Igor_z_Emmanuelplatz_oghss4', 'career_id' => 194,
                'stats_cur' => [30,27,35,35,32,28,31,25, 1,12,3,3,4,0, 0,2],
                'stats_start' => [33,29,31,33,28,26,32,31, 1,12,3,3,4,0, 0,2],
                'stats_adv' => [0,10,10,5,10,5,5,0, 0,2,0,0,0,0, 0,0],
                'skills' => ['Zastraszanie', 'Unik', 'Bijatyka', 'Mocna Głowa', 'Hazard'],
                'talents' => ['Groźny', 'Uliczny Wojownik', 'Bardzo Silny'],
                'meta' => ['Barti', 'Vojas', 2, 2522]
            ],
            [
                'id' => 4, 'usedname' => 'Richard Krupse', 'name' => 'Handblat "Jaworek" Schmidt', 'breed' => 'człowiek', 'sex' => 'Mężczyzna', 'age' => 29,
                'height' => 177, 'weight' => 85, 'hair' => 'brązowe', 'eyes' => 'czarne', 'sign' => 'Tłusty Kozioł (15)', 'siblings' => '1 brat', 'place' => 'Nuln', 'special' => 'tatuaż',
                'history' => 'Urodziłeś się ćwierć wieku temu w niezbyt zamożnej rodzinie z Nuln...',
                'god' => 'Mannan', 'avatar' => 'Richard_Krupse_csqij4', 'career_id' => 100,
                'stats_cur' => [30,27,35,35,32,28,31,35, 1,11,3,3,4,0, 0,3],
                'stats_start' => [32,24,35,37,35,31,29,33, 1,11,3,3,4,0, 0,3],
                'stats_adv' => [0,10,10,5,10,5,5,0, 0,2,0,0,0,0, 0,0],
                'skills' => ['Wycena', 'Targowanie', 'Plotkowanie', 'Czytanie i Pisanie', 'Sekretny Język (Gildii)'],
                'talents' => ['Żyłka Handlowa', 'Obieżyświat'],
                'meta' => ['Dinio', 'Vojas', 4, 2522]
            ],
            [
                'id' => 5, 'usedname' => 'Fergale Noimann', 'name' => 'Fergale Noimann', 'breed' => 'człowiek', 'sex' => 'Kobieta', 'age' => 29,
                'height' => 167, 'weight' => 70, 'hair' => 'jasnobrązowe', 'eyes' => 'piwne', 'sign' => 'Wół Gnuthus (18)', 'siblings' => '3 rodzeństwa, ojciec', 'place' => 'Vandengart w Ostlandzie', 'special' => 'Drugie oko niebieskie',
                'history' => 'Lorem ipsum dolor sit amet...',
                'god' => 'Mannan', 'avatar' => 'Fergale_Noimann_tyokav', 'career_id' => 193,
                'stats_cur' => [36,32,33,36,38,37,31,37, 1,12,3,3,4,0, 2,3],
                'stats_start' => [31,32,33,36,38,37,31,37, 1,12,3,3,4,0, 0,3],
                'stats_adv' => [5,5,0,0,10,10,0,10, 0,2,0,0,0,0, 0,0],
                'skills' => ['Unik', 'Przeszukiwanie', 'Spostrzegawczość'],
                'talents' => ['Szczęście'],
                'meta' => ['Mari', 'Vojas', 3, 2521]
            ],
            [
                'id' => 6, 'usedname' => 'Otfried Breuer', 'name' => 'Otfried Breuer', 'breed' => 'człowiek', 'sex' => 'Mężczyzna', 'age' => 33,
                'height' => 171, 'weight' => 95, 'hair' => 'ciemny blond', 'eyes' => 'zielone', 'sign' => 'Smok Dragomas (12)', 'siblings' => '3 rodzeństwa', 'place' => 'Segeldorf w Wissenlandzie', 'special' => 'Tatuaż',
                'history' => 'Lorem ipsum dolor sit amet...',
                'god' => 'Mannan', 'avatar' => 'Otfried_Breuer_zpp3nm', 'career_id' => 194,
                'stats_cur' => [30,27,40,35,32,28,31,25, 1,11,4,3,4,0, 3,2],
                'stats_start' => [30,27,35,35,32,28,31,25, 1,11,4,3,4,0, 0,2],
                'stats_adv' => [0,10,10,5,10,5,5,0, 0,2,0,0,0,0, 0,0],
                'skills' => ['Wioślarstwo', 'Pływanie', 'Mocna Głowa'],
                'talents' => ['Krzepki'],
                'meta' => ['Kaczmar', 'Vojas', 3, 2521]
            ],
            [
                'id' => 7, 'usedname' => 'Drubik Grzechotek', 'name' => 'Drubik Grzechotek', 'breed' => 'niziołek', 'sex' => 'Mężczyzna', 'age' => 36,
                'height' => 112, 'weight' => 45, 'hair' => 'popielate', 'eyes' => 'jasnobrązowe', 'sign' => 'Kocioł Rhyi (07)', 'siblings' => 'brat, rodzice', 'place' => 'Sigmarheim w Lyonesse', 'special' => 'Duży pieprzyk',
                'history' => 'Lorem ipsum dolor sit amet...',
                'god' => 'Taal', 'avatar' => 'Drubik_Grzechotek_dudcg7', 'career_id' => 19,
                'stats_cur' => [20,53,15,41,41,31,27,42, 1,12,1,4,4,0, 3,2],
                'stats_start' => [20,48,15,36,41,31,27,42, 1,11,1,4,4,0, 0,2],
                'stats_adv' => [0,15,0,5,10,5,0,0, 0,3,0,0,0,0, 0,0],
                'skills' => ['Skradanie się', 'Ukrywanie się', 'Gotowanie', 'Hazard', 'Wiedza (Niziołki)'],
                'talents' => ['Odporność na Chaos', 'Widzenie w Ciemności'],
                'meta' => ['maciek4438@wp.pl', 'Vojas', 3, 2521]
            ],
            [
                'id' => 8, 'usedname' => 'Nor Gil Ollason', 'name' => 'Tugor Barriksson', 'breed' => 'krasnolud', 'sex' => 'Mężczyzna', 'age' => 98,
                'height' => 157, 'weight' => 75, 'hair' => 'Jasny Brąz', 'eyes' => 'piwne', 'sign' => 'Smok Dragomas (12)', 'siblings' => 'brat, rodzice', 'place' => 'Karak Kadrim', 'special' => 'Długie Włosy',
                'history' => 'Lorem ipsum dolor sit amet...',
                'god' => 'Grimnir', 'avatar' => 'Nor_Gil_Ollason_y7n06a', 'career_id' => 72,
                'stats_cur' => [36,32,36,42,26,50,35,34, 1,14,3,3,3,0, 3,2],
                'stats_start' => [36,32,36,42,16,40,30,24, 1,12,3,3,3,0, 0,2],
                'stats_adv' => [20,15,10,10,10,10,10,20, 1,4,0,0,0,0, 0,0],
                'skills' => ['Wiedza (Krasnoludy)', 'Rzemiosło (Kowalstwo)', 'Wycena', 'Górnictwo'],
                'talents' => ['Krasnoludzki Fach', 'Ziekła Nienawiść', 'Odporność na Magię'],
                'meta' => ['GM', 'Vojas', 3, 2521]
            ],
            [
                'id' => 9, 'usedname' => 'Thorgrek Bellison', 'name' => 'Nor Gil Ollason', 'breed' => 'krasnolud', 'sex' => 'Mężczyzna', 'age' => 94,
                'height' => 157, 'weight' => 55, 'hair' => 'Jasny Brąz', 'eyes' => 'piwne', 'sign' => 'Smok Dragomas (12)', 'siblings' => 'siostra, brak ojca, matka', 'place' => 'Karak Azgal', 'special' => 'Długie Włosy',
                'history' => 'Lorem ipsum dolor sit amet...',
                'god' => 'Grunghi', 'avatar' => 'Thorgrek_Bellison_oqjqnw', 'career_id' => 72,
                'stats_cur' => [44,30,33,40,35,55,35,29, 1,14,3,4,3,0, 1,1],
                'stats_start' => [44,30,33,40,25,45,30,19, 1,12,3,4,3,0, 0,1],
                'stats_adv' => [10,15,5,5,10,20,10,10, 0,4,0,0,0,0, 0,0],
                'skills' => ['Wiedza (Krasnoludy)', 'Rzemiosło (Kamieniarstwo)'],
                'talents' => ['Krasnoludzki Fach', 'Ziekła Nienawiść', 'Odporność na Magię'],
                'meta' => ['GM', 'Vojas', 3, 2521]
            ],
            [
                'id' => 10, 'usedname' => 'Gregor Mosqual', 'name' => 'Gregor Mosqual', 'breed' => 'człowiek', 'sex' => 'Mężczyzna', 'age' => 28,
                'height' => 186, 'weight' => 90, 'hair' => 'Czarne', 'eyes' => 'niebieskie', 'sign' => 'Bębniarz (01)', 'siblings' => '3 siostry', 'place' => 'Middenheim, Middenland', 'special' => 'Długie Włosy',
                'history' => 'Lorem ipsum dolor sit amet...',
                'god' => 'Taal', 'avatar' => null, 'career_id' => 40,
                'stats_cur' => [42,40,39,33,45,46,37,44, 1,15,3,4,4,0, 1,2],
                'stats_start' => [37,35,34,33,30,41,27,34, 1,12,3,4,4,0, 0,2],
                'stats_adv' => [10,10,5,5,25,10,10,10, 0,4,0,0,0,0, 0,0],
                'skills' => ['Unik', 'Przeszukiwanie', 'Spostrzegawczość'],
                'talents' => ['Szczęście'],
                'meta' => ['GM', 'Vojas', 3, 2521]
            ],
            [
                'id' => 11, 'usedname' => 'Othra Lun Gronikson', 'name' => 'Othra Lun Gronikson', 'breed' => 'krasnolud', 'sex' => 'Mężczyzna', 'age' => 75,
                'height' => 155, 'weight' => 85, 'hair' => 'popielate', 'eyes' => 'brązowe', 'sign' => 'Smok Dragomas (12)', 'siblings' => 'brat, rodzice', 'place' => 'Karak Norn', 'special' => 'Wystające zęby',
                'history' => 'Lorem ipsum dolor sit amet...',
                'god' => 'Grimnir', 'avatar' => null, 'career_id' => 16,
                'stats_cur' => [48,24,32,46,12,31,36,21, 1,14,3,4,3,1, 3,2],
                'stats_start' => [48,24,32,46,12,31,36,21, 1,14,3,4,3,1, 0,2],
                'stats_adv' => [5,0,5,0,0,10,15,0, 0,2,0,0,0,0, 0,0],
                'skills' => ['Wiedza (Krasnoludy)', 'Rzemiosło (Kowalstwo)'],
                'talents' => ['Krasnoludzki Fach', 'Ziekła Nienawiść', 'Odporność na Magię'],
                'meta' => ['GM', 'Vojas', 3, 2521]
            ],
            [
                'id' => 12, 'usedname' => 'Urdithane', 'name' => 'Urdithane', 'breed' => 'Elf', 'sex' => 'Mężczyzna', 'age' => 18,
                'height' => 180, 'weight' => 50, 'hair' => 'Czarne', 'eyes' => 'Brązowe', 'sign' => 'Wielki Krzyż (17)', 'siblings' => 'brak rodziny', 'place' => 'Las Laurelon', 'special' => 'Blizna na czole',
                'history' => '<div>Lorem ipsum dolor sit amet...</div><div><strong>Cyrkowiec</strong></div>',
                'god' => 'Hoeth', 'avatar' => null, 'career_id' => 19,
                'stats_cur' => [35,40,28,31,42,27,30,36, 1,11,2,3,5,0, 3,2],
                'stats_start' => [35,40,28,31,42,27,30,36, 1,11,2,3,5,0, 0,2],
                'stats_adv' => [5,10,0,0,10,0,5,10, 0,2,0,0,0,0, 0,0],
                'skills' => ['Kuglarstwo (Żonglerka)', 'Kuglarstwo (Taniec)', 'Spostrzegawczość', 'Język (Eltharin)', 'Język (Staroświatowy)'],
                'talents' => ['Bystry Wzrok', 'Nocne Widzenie'],
                'meta' => ['GM', 'Vojas', 3, 2521]
            ],
            [
                'id' => 13, 'usedname' => 'Ethan Belly', 'name' => 'Ethan Belly', 'breed' => 'Niziołek', 'sex' => 'Mężczyzna', 'age' => 44,
                'height' => 120, 'weight' => 45, 'hair' => 'Brązowe', 'eyes' => 'Jasnobrązowe', 'sign' => 'Sznur Limnera (13)', 'siblings' => '2 brat i siostra, rodzice', 'place' => 'Halheim, Reikland', 'special' => 'Długie Włosy',
                'history' => '<div>Lorem ipsum dolor sit amet...</div><div><strong>Włóczykij</strong></div>',
                'god' => 'Grimnir', 'avatar' => null, 'career_id' => 96,
                'stats_cur' => [30,50,23,28,45,31,27,42, 1,10,2,2,4,0, 1,2],
                'stats_start' => [25,45,23,28,45,31,27,42, 1,10,2,2,4,0, 0,2],
                'stats_adv' => [5,15,0,0,10,0,0,5, 0,2,0,0,1,0, 0,0],
                'skills' => ['Skradanie się', 'Gotowanie', 'Nawigacja', 'Survival'],
                'talents' => ['Odporność na Chaos', 'Nocne Widzenie'],
                'meta' => ['GM', 'Vojas', 3, 2521]
            ],
            [
                'id' => 14, 'usedname' => 'And I Teal', 'name' => 'And I Teal', 'breed' => 'Elf', 'sex' => 'Kobieta', 'age' => 75,
                'height' => 169, 'weight' => 70, 'hair' => 'Brązowe', 'eyes' => 'Kasztan&Niebies', 'sign' => 'Dudy (02)', 'siblings' => '1 Brat', 'place' => 'Altdorf', 'special' => 'Oczy różnego koloru',
                'history' => '<div><strong>Kanciarz</strong></div>',
                'god' => 'Hoeth', 'avatar' => null, 'career_id' => 33,
                'stats_cur' => [32,49,34,36,44,41,42,30, 1,10,3,3,5,0, 0,2],
                'stats_start' => [32,49,34,36,44,41,42,25, 1,10,3,3,5,0, 0,2],
                'stats_adv' => [5,5,0,0,10,5,5,10, 0,2,0,0,0,0, 0,0],
                'skills' => ['Hazard', 'Plotkowanie', 'Charyzma', 'Język (Staroświatowy)'],
                'talents' => ['Szczęście', 'Bystry Wzrok', 'Nocne Widzenie'],
                'meta' => ['GM', 'Vojas', 3, 2521]
            ],
            [
                'id' => 15, 'usedname' => 'Ruben Baher', 'name' => 'Ruben Baher', 'breed' => 'Człowiek', 'sex' => 'Mężczyzna', 'age' => 43,
                'height' => 176, 'weight' => 82, 'hair' => 'Ciemno rude', 'eyes' => 'Jasnobrązowe', 'sign' => 'Bębniarz (01)', 'siblings' => '1 siostra, 1 brat, brak matki', 'place' => 'Norderingen, Middenland', 'special' => 'Wytrzeszczone oczy',
                'history' => 'Obecna: Odkrywca; Poprzednie: Sługa, Szpieg',
                'god' => 'Sigmar', 'avatar' => 'Ruben_Baher_yrubpq', 'career_id' => 58,
                'stats_cur' => [44,24,35,48,53,40,55,35, 2,15,3,4,5,0, 1,3],
                'stats_start' => [39,24,30,48,43,35,45,30, 1,13,3,4,4,0, 0,3],
                'stats_adv' => [15,15,5,10,20,20,35,20, 1,2,0,0,0,0, 0,0],
                'skills' => ['Śledzenie', 'Skradanie się', 'Otwieranie Zamków', 'Czytanie z Warg'],
                'talents' => ['Szósty Zmysł', 'Ulicznik', 'Błyskawiczny Refleks'],
                'meta' => ['GM', 'Vojas', 4, 2521]
            ],
            [
                'id' => 16, 'usedname' => 'Kleber Vogel', 'name' => 'Kleber Vogel', 'breed' => 'Człowiek', 'sex' => 'Mężczyzna', 'age' => 25,
                'height' => 180, 'weight' => 80, 'hair' => 'Popielaty', 'eyes' => 'Piwne', 'sign' => 'Gwiazda Wieczorna (06)', 'siblings' => '4 rodzeństwa', 'place' => 'Wolfenburg,  Ostland', 'special' => 'Brak palca',
                'history' => '<div>Węglarz</div>',
                'god' => 'Sigmar', 'avatar' => null, 'career_id' => 50,
                'stats_cur' => [33,31,41,32,34,31,27,29, 1,11,4,3,4,0, 1,3],
                'stats_start' => [33,31,36,32,34,31,27,29, 1,11,3,3,4,0, 0,3],
                'stats_adv' => [5,0,5,5,5,5,5,5, 0,2,0,0,0,0, 0,0],
                'skills' => ['Wiedza (Lasy)', 'Powroźnictwo', 'Targowanie'],
                'talents' => ['Bardzo Silny'],
                'meta' => ['GM', 'Vojas', 3, 2521]
            ],
            [
                'id' => 17, 'usedname' => 'Sigrid Brokkson', 'name' => 'Sigrid Brokkson', 'breed' => 'Krasnolud', 'sex' => 'Mężczyzna', 'age' => 30,
                'height' => 153, 'weight' => 80, 'hair' => 'Ciemno rude', 'eyes' => 'Fioletowe', 'sign' => 'Vobist Ulotny (16)', 'siblings' => '2', 'place' => 'Zhufbar', 'special' => 'Brak zęba',
                'history' => '_nowy_BG',
                'god' => 'Grimnir', 'avatar' => null, 'career_id' => 46,
                'stats_cur' => [55,36,29,44,24,39,32,19, 1,13,2,4,3,0, 0,1],
                'stats_start' => [50,36,29,44,24,39,32,19, 1,13,2,4,3,0, 0,1],
                'stats_adv' => [10,5,0,0,5,5,5,10, 0,2,0,0,0,0, 0,0],
                'skills' => ['Wiedza (Krasnoludy)', 'Rzemiosło (Górnictwo)'],
                'talents' => ['Krasnoludzki Fach', 'Odporność na Magię'],
                'meta' => ['GM', 'Vojas', 3, 2522]
            ],
            [
                'id' => 18, 'usedname' => 'Albrecht Wolf', 'name' => 'Albrecht Wolf', 'breed' => 'Człowiek', 'sex' => 'Mężczyzna', 'age' => 24,
                'height' => 169, 'weight' => 70, 'hair' => 'Brązowe', 'eyes' => 'Jasny brąz', 'sign' => 'Wymond (19)', 'siblings' => '0', 'place' => 'Reikland - Weissbruck', 'special' => 'Tatuaż, niedosłuch.',
                'history' => '<div>Kiedy byłem mały, moja mama została uznana, przez łowcę czarownic za wiedźmę...</div>',
                'god' => 'Sigmar', 'avatar' => 'Albrecht_Wolf_fnwu3v', 'career_id' => 8,
                'stats_cur' => [31,23,30,35,30,26,29,32, 1,11,3,3,4,0, 0,3],
                'stats_start' => [31,23,30,35,30,26,29,32, 1,11,3,3,4,0, 0,3],
                'stats_adv' => [10,0,5,10,0,0,10,5, 0,2,0,0,0,0, 0,0],
                'skills' => ['Wiedza (Teologia)', 'Czytanie i Pisanie', 'Język (Staroświatowy)'],
                'talents' => ['Charyzmatyczny', 'Bystry Wzrok'],
                'meta' => ['Piotr', 'Vojas', 4, 2521]
            ],
            [
                'id' => 19, 'usedname' => 'Jurgen Baer', 'name' => 'Jurgen Baer', 'breed' => 'Człowiek', 'sex' => 'Mężczyzna', 'age' => 42,
                'height' => 173, 'weight' => 70, 'hair' => 'Brązowe', 'eyes' => 'Piwne', 'sign' => 'Gwiazda Wieczorna (06)', 'siblings' => '2', 'place' => 'Nuln', 'special' => 'Poszarpane ucho',
                'history' => 'Obecna: Żeglarz; Poprzednie: Przemytnik',
                'god' => 'Wybierz', 'avatar' => 'Jurgen_Baer_kriqoo', 'career_id' => 58,
                'stats_cur' => [42,27,25,34,41,49,48,36, 1,12,2,3,4,0, 0,0],
                'stats_start' => [32,27,25,29,31,39,38,31, 1,10,2,2,4,0, 0,0],
                'stats_adv' => [15,10,10,5,10,10,10,10, 1,4,0,0,0,0, 0,0],
                'skills' => ['Żeglarstwo', 'Pływanie', 'Unik', 'Plotkowanie', 'Wiedza (Jałowa Kraina)'],
                'talents' => ['Bijatyka', 'Uliczny Wojownik'],
                'meta' => ['GM', 'Vojas', 4, 2521]
            ],
            [
                'id' => 20, 'usedname' => 'Horst Breuer', 'name' => 'Horst Breuer', 'breed' => 'Człowiek', 'sex' => 'Mężczyzna', 'age' => 0,
                'height' => 174, 'weight' => 75, 'hair' => 'Losuj', 'eyes' => 'Losuj', 'sign' => 'Gwiazda Wieczorna (06)', 'siblings' => 'Losuj', 'place' => 'Losuj', 'special' => 'Losuj',
                'history' => '<div>doker</div><div>&nbsp;</div><div>broń ręczna pałka (k10 +4)</div>',
                'god' => 'Wybierz', 'avatar' => null, 'career_id' => 208,
                'stats_cur' => [36,32,45,42,41,35,43,39, 1,11,4,4,4,0, 0,0],
                'stats_start' => [36,32,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'stats_adv' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'skills' => ['Dowodzenie', 'Hazard', 'Kuglarstwo (Śpiew)', 'Mocna Głowa', 'Plotkowanie', 'Pływanie', 'Sekretny Język (Gildii)', 'Spostrzegawczość', 'Targowanie', 'Unik', 'Wiedza (Jałowa Kraina)', 'Język (Staroświatowy)'],
                'talents' => ['Bardzo Silny', 'Łotrzyk', 'Odporność Psychiczna', 'Opanowanie', 'Przemawianie', 'Szybki Refleks'],
                'meta' => ['GM', 'Vojas', 4, 2521]
            ],
            [
                'id' => 21, 'usedname' => 'Rufus Betz', 'name' => 'Ravandil', 'breed' => 'Elf', 'sex' => 'Mężczyzna', 'age' => 295,
                'height' => 185, 'weight' => 85, 'hair' => 'Ciemnobrązowe', 'eyes' => 'Kasztanowe', 'sign' => 'Gwiazda Wieczorna (06)', 'siblings' => '1', 'place' => 'Marienburg', 'special' => 'Blada cera',
                'history' => '_nowy_BG',
                'god' => 'Wybierz', 'avatar' => 'Rufus-Betz_wzmumh', 'career_id' => 113,
                'stats_cur' => [41,59,26,49,55,58,59,49, 1,15,2,4,5,3, 0,1],
                'stats_start' => [31,49,26,39,45,38,29,39, 1,11,2,3,5,0, 0,1],
                'stats_adv' => [10,10,0,10,15,30,35,15, 0,4,0,0,0,3, 0,0],
                'skills' => ['Magia Powszechna', 'Magia Tajemna (Ogień)', 'Język (Magiczny)', 'Wiedza (Magia)'],
                'talents' => ['Magia Prosta (Tajemna)', 'Medytacja', 'Zmysł Magii'],
                'meta' => ['GM', 'Vojas', 4, 2521]
            ],
            [
                'id' => 22, 'usedname' => 'Tugor Barriksson', 'name' => '_nowy_BG', 'breed' => 'krasnolud', 'sex' => 'Mężczyzna', 'age' => 0,
                'height' => 0, 'weight' => 0, 'hair' => 'Losuj', 'eyes' => 'Losuj', 'sign' => 'Losuj', 'siblings' => 'Losuj', 'place' => 'Losuj', 'special' => 'Losuj',
                'history' => '_nowy_BG',
                'god' => 'Wybierz', 'avatar' => 'Nor_Gil_Ollason_y7n06a', 'career_id' => 1,
                'stats_cur' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'stats_start' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'stats_adv' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'skills' => [],
                'talents' => [],
                'meta' => ['GM', 'Vojas', 3, 2521]
            ],
            [
                'id' => 23, 'usedname' => 'Tel Aes In', 'name' => 'Telaesin Es Brin', 'breed' => 'Elf', 'sex' => 'Mężczyzna', 'age' => 125,
                'height' => 183, 'weight' => 90, 'hair' => 'Czarne', 'eyes' => 'Brązowe', 'sign' => 'Gwiazda Wieczorna (06)', 'siblings' => 'troje braci', 'place' => 'Marienburg', 'special' => 'niewielka łysina',
                'history' => '<div>Telaesin Es Brin wywodzi się z jednej z większych elfickich kupieckich rodzin w Marienburgu...</div>',
                'god' => 'Hoeth', 'avatar' => 'Telaesin_g6hfpk', 'career_id' => 1,
                'stats_cur' => [31,38,26,25,46,53,48,28, 1,9,2,2,5,1, 0,2],
                'stats_start' => [31,38,26,25,46,48,38,28, 1,9,2,2,5,1, 0,2],
                'stats_adv' => [0,0,0,0,5,10,15,5, 0,2,0,0,0,1, 0,0],
                'skills' => ['Wycena', 'Targowanie', 'Czytanie i Pisanie', 'Język (Eltharin)', 'Wiedza (Histora)'],
                'talents' => ['Bystry Wzrok', 'Nocne Widzenie'],
                'meta' => ['Paweł', 'Vojas', 4, 2521]
            ],
            [
                'id' => 24, 'usedname' => 'Heinz Breuer', 'name' => 'Heinz Breuer', 'breed' => 'Człowiek', 'sex' => 'Mężczyzna', 'age' => 21,
                'height' => 170, 'weight' => 110, 'hair' => 'Jasnobrązowe', 'eyes' => 'Zielone', 'sign' => 'Tłusty Kozioł (15)', 'siblings' => '2 siostry bliźniaczki', 'place' => 'Altdorf', 'special' => 'Brodawki z tyłu głowy',
                'history' => 'brodawki z tyłu głowy, 2 rodzeństwa- siostry bliźniaczki...',
                'god' => 'Haendryk', 'avatar' => 'Heinz_Breuer_wwwbkr', 'career_id' => 1,
                'stats_cur' => [27,35,28,37,27,44,50,34, 1,12,2,3,4,1, 0,3],
                'stats_start' => [27,35,28,37,27,39,40,34, 1,12,2,3,4,1, 0,3],
                'stats_adv' => [0,0,0,0,5,10,15,5, 0,2,0,0,0,1, 0,0],
                'skills' => ['Plotkowanie', 'Przekonywanie', 'Język (Staroświatowy)'],
                'talents' => ['Mocna Głowa'],
                'meta' => ['Maciek', 'Vojas', 4, 2521]
            ],
            [
                'id' => 25, 'usedname' => 'Maksym Ivanowicz Borodin', 'name' => 'Maksym Ivanowicz Borodin', 'breed' => 'Człowiek', 'sex' => 'Mężczyzna', 'age' => 28,
                'height' => 163, 'weight' => 100, 'hair' => 'Ciemnorude', 'eyes' => 'Jasnobrązowe', 'sign' => 'Mędrzec Mammit (09)', 'siblings' => '1 brat', 'place' => 'Erengrad', 'special' => NULL,
                'history' => '<div>Maksym Iwanowicz Borodin, Kozak Ksilevski...</div>',
                'god' => 'Ursun', 'avatar' => 'Maksym_Ivanowicz_Borodin_e8vxw7', 'career_id' => 17,
                'stats_cur' => [32,26,37,34,37,37,27,28, 1,10,3,3,4,0, 0,3],
                'stats_start' => [27,26,37,34,37,37,27,28, 1,10,3,3,4,0, 0,3],
                'stats_adv' => [10,10,0,10,0,0,10,0, 0,2,0,0,0,0, 0,0],
                'skills' => ['Jeździectwo', 'Opieka nad Zwierzętami', 'Wiedza (Kislev)'],
                'talents' => ['Urodzony Wojownik', 'Twardziel'],
                'meta' => ['Radek', 'Vojas', 4, 2521]
            ],
            [
                'id' => 26, 'usedname' => 'Klitko Majkin', 'name' => 'Klitko Majkin', 'breed' => 'Człowiek', 'sex' => 'Mężczyzna', 'age' => 28,
                'height' => 169, 'weight' => 80, 'hair' => 'Czarne', 'eyes' => 'Sjenowe', 'sign' => 'Smok Dragomas (12)', 'siblings' => '2', 'place' => 'Kislev-Sandora', 'special' => 'Losuj',
                'history' => 'Służę krajowi (KISLEV)',
                'god' => 'Wybierz', 'avatar' => 'Klitko_Majkin_ketklq', 'career_id' => 208,
                'stats_cur' => [36,32,45,42,41,35,43,39, 1,11,4,4,4,0, 0,0],
                'stats_start' => [39,30,38,34,33,23,32,35, 1,11,4,4,4,0, 0,0],
                'stats_adv' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'skills' => ['Unik', 'Język (Kislev)'],
                'talents' => [],
                'meta' => ['Weronik', 'Vojas', 4, 2521]
            ],
            [
                'id' => 27, 'usedname' => 'Vitko Majkin', 'name' => 'Vitko Majkin', 'breed' => 'Człowiek', 'sex' => 'Mężczyzna', 'age' => 28,
                'height' => 169, 'weight' => 80, 'hair' => 'Czarne', 'eyes' => 'Sjenowe', 'sign' => 'Smok Dragomas (12)', 'siblings' => '2', 'place' => 'Kislev-Sandora', 'special' => 'Losuj',
                'history' => '_nowy_BN',
                'god' => 'Wybierz', 'avatar' => 'Vitko_ibmwy1', 'career_id' => 208,
                'stats_cur' => [39,30,38,34,33,23,32,35, 1,11,3,3,4,0, 0,3],
                'stats_start' => [39,30,38,34,33,23,32,35, 1,11,3,3,4,0, 0,3],
                'stats_adv' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'skills' => ['Unik', 'Język (Kislev)'],
                'talents' => [],
                'meta' => ['GM', 'Vojas', 4, 2521]
            ],
            // Placeholdery
            [
                'id' => 28, 'usedname' => 'Lupus Riese', 'name' => 'Lupus Riese', 'breed' => 'Człowiek', 'sex' => 'Mężczyzna', 'age' => 0,
                'height' => 0, 'weight' => 0, 'hair' => 'Losuj', 'eyes' => 'Losuj', 'sign' => 'Losuj', 'siblings' => 'Losuj', 'place' => 'Losuj', 'special' => 'Losuj',
                'history' => 'dziadek żony+mąż wnuczki', 'god' => 'Wybierz', 'avatar' => null, 'career_id' => 1,
                'stats_cur' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_start' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_adv' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'skills' => [], 'talents' => [],
                'meta' => ['GM', 'Vojas', 3, 2521]
            ],
            [
                'id' => 29, 'usedname' => 'Meinhart Noimann', 'name' => 'Meinhart Noimann', 'breed' => 'Człowiek', 'sex' => 'Mężczyzna', 'age' => 0,
                'height' => 0, 'weight' => 0, 'hair' => 'Losuj', 'eyes' => 'Losuj', 'sign' => 'Losuj', 'siblings' => 'Losuj', 'place' => 'Losuj', 'special' => 'Losuj',
                'history' => 'stryj Vitona', 'god' => 'Wybierz', 'avatar' => null, 'career_id' => 1,
                'stats_cur' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_start' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_adv' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'skills' => [], 'talents' => [],
                'meta' => ['GM', 'Vojas', 3, 2521]
            ],
            [
                'id' => 30, 'usedname' => 'Viton Noimann', 'name' => 'Viton Noimann', 'breed' => 'Człowiek', 'sex' => 'Mężczyzna', 'age' => 0,
                'height' => 0, 'weight' => 0, 'hair' => 'Losuj', 'eyes' => 'Losuj', 'sign' => 'Losuj', 'siblings' => 'Losuj', 'place' => 'Losuj', 'special' => 'Losuj',
                'history' => 'przyjaciel Petera Koniga', 'god' => 'Wybierz', 'avatar' => null, 'career_id' => 1,
                'stats_cur' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_start' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_adv' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'skills' => [], 'talents' => [],
                'meta' => ['GM', 'Vojas', 3, 2521]
            ],
            [
                'id' => 31, 'usedname' => 'Peter Koning', 'name' => 'Peter Koning', 'breed' => 'Człowiek', 'sex' => 'Mężczyzna', 'age' => 0,
                'height' => 0, 'weight' => 0, 'hair' => 'Losuj', 'eyes' => 'Losuj', 'sign' => 'Losuj', 'siblings' => 'Losuj', 'place' => 'Losuj', 'special' => 'Losuj',
                'history' => 'Właściciel karczmy Przy brzegu w L\'Anguille', 'god' => 'Wybierz', 'avatar' => null, 'career_id' => 1,
                'stats_cur' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_start' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_adv' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'skills' => [], 'talents' => [],
                'meta' => ['GM', 'Vojas', 3, 2521]
            ],
            [
                'id' => 32, 'usedname' => 'Amol', 'name' => 'Ar Mor On Lain', 'breed' => '_nowy_BG', 'sex' => 'Mężczyzna', 'age' => 0,
                'height' => 0, 'weight' => 0, 'hair' => 'Losuj', 'eyes' => 'Losuj', 'sign' => 'Losuj', 'siblings' => 'Losuj', 'place' => 'Losuj', 'special' => 'Losuj',
                'history' => '_nowy_BG', 'god' => 'Wybierz', 'avatar' => null, 'career_id' => 1,
                'stats_cur' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_start' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_adv' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'skills' => [], 'talents' => [],
                'meta' => ['GM', 'Vojas', 4, 2521]
            ],
            [
                'id' => 33, 'usedname' => 'Strażnik Tradycji', 'name' => 'Psylithar Gigardian', 'breed' => '_nowy_BG', 'sex' => 'Mężczyzna', 'age' => 0,
                'height' => 0, 'weight' => 0, 'hair' => 'Losuj', 'eyes' => 'Losuj', 'sign' => 'Losuj', 'siblings' => 'Losuj', 'place' => 'Losuj', 'special' => 'Losuj',
                'history' => '_nowy_BG', 'god' => 'Wybierz', 'avatar' => null, 'career_id' => 1,
                'stats_cur' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_start' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_adv' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'skills' => [], 'talents' => [],
                'meta' => ['GM', 'Vojas', 4, 2521]
            ],
            [
                'id' => 34, 'usedname' => 'Marius Kampf', 'name' => '_nowy_BG', 'breed' => '_nowy_BG', 'sex' => 'Mężczyzna', 'age' => 0,
                'height' => 0, 'weight' => 0, 'hair' => 'Losuj', 'eyes' => 'Losuj', 'sign' => 'Losuj', 'siblings' => 'Losuj', 'place' => 'Losuj', 'special' => 'Losuj',
                'history' => '_nowy_BG', 'god' => 'Wybierz', 'avatar' => null, 'career_id' => 1,
                'stats_cur' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_start' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_adv' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'skills' => [], 'talents' => [],
                'meta' => ['GM', 'Vojas', 4, 2521]
            ],
            [
                'id' => 35, 'usedname' => 'Elise', 'name' => 'von Lichtenau', 'breed' => 'Człowiek', 'sex' => 'Mężczyzna', 'age' => 0,
                'height' => 0, 'weight' => 0, 'hair' => 'Losuj', 'eyes' => 'Losuj', 'sign' => 'Losuj', 'siblings' => 'Losuj', 'place' => 'Losuj', 'special' => 'Losuj',
                'history' => '_nowy_BG', 'god' => 'Wybierz', 'avatar' => null, 'career_id' => 1,
                'stats_cur' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_start' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_adv' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'skills' => [], 'talents' => [],
                'meta' => ['GM', 'Vojas', 1, 2521]
            ],
            [
                'id' => 36, 'usedname' => 'Adelinde', 'name' => 'Baher', 'breed' => 'Człowiek', 'sex' => 'Mężczyzna', 'age' => 0,
                'height' => 0, 'weight' => 0, 'hair' => 'Losuj', 'eyes' => 'Losuj', 'sign' => 'Losuj', 'siblings' => 'Losuj', 'place' => 'Losuj', 'special' => 'Losuj',
                'history' => '_nowy_BG', 'god' => 'Wybierz', 'avatar' => null, 'career_id' => 1,
                'stats_cur' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_start' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0], 'stats_adv' => [0,0,0,0,0,0,0,0, 0,0,0,0,0,0, 0,0],
                'skills' => [], 'talents' => [],
                'meta' => ['GM', 'Vojas', 1, 2521]
            ]
        ];

        // ==========================================
        // INSERT DO BAZY
        // ==========================================
        $charBuilder = $db->table('characters');
        $count = 0;

        foreach ($sourceData as $char) {
            
            // Mapowanie statystyk na klucze (16 cech) - Kolejność z karty postaci WFRP
            // WW, US, K, Odp, Zr, Int, SW, Ogd, A, Zyw, S, Wt, Sz, Mag, PO, PP
            $keys = ['ww', 'us', 'k', 'odp', 'zr', 'int', 'sw', 'ogd', 'a', 'zyw', 's', 'wt', 'sz', 'mag', 'po', 'pp'];
            
            // Helper do mapowania tablicy numerycznej na asocjacyjną
            $mapStats = function($values) use ($keys) {
                $mapped = [];
                foreach ($keys as $i => $key) {
                    $mapped[$key] = $values[$i] ?? 0;
                }
                return $mapped;
            };

            $attributes = [];
            $attributes['start']    = $mapStats($char['stats_start']);
            $attributes['advances'] = $mapStats($char['stats_adv']);
            $attributes['actual']   = $mapStats($char['stats_cur']);
            
            // Dodajemy umiejętności i zdolności (teraz są już wypełnione w sourceData)
            $attributes['skills']   = $char['skills'];
            $attributes['talents']  = $char['talents'];

            $jsonData = [
                'details' => [
                    'name'          => $char['usedname'],
                    'true_name'     => $char['name'],
                    'race'          => $char['breed'],
                    'sex'           => $char['sex'],
                    'age'           => $char['age'],
                    'height'        => $char['height'],
                    'weight'        => $char['weight'],
                    'hair_color'    => $char['hair'],
                    'eye_color'     => $char['eyes'],
                    'star_sign'     => $char['sign'],
                    'siblings'      => $char['siblings'],
                    'birthplace'    => $char['place'],
                    'special_signs' => $char['special'],
                    'history'       => $char['history'],
                    'deity'         => $char['god'],
                    'profession_id' => $char['career_id'],
                ],
                'attributes' => $attributes,
                'meta' => [
                    'gamer_name'    => $char['meta'][0],
                    'game_master'   => $char['meta'][1],
                    'campaign_name' => $char['meta'][2],
                    'campaign_year' => $char['meta'][3]
                ]
            ];

            // Sprawdzamy duplikaty po nazwie
            $exists = $charBuilder->where('name', $char['usedname'])->countAllResults();
            
            if ($exists == 0) {
                $charBuilder->insert([
                    'user_id'     => null,
                    'campaign_id' => null, 
                    'system_id'   => $sysId,
                    'universe_id' => $uniId,
                    'name'        => $char['usedname'],
                    'avatar_url'  => $char['avatar'],
                    'data'        => json_encode($jsonData, JSON_UNESCAPED_UNICODE),
                    'created_at'  => date('Y-m-d H:i:s')
                ]);
                $count++;
            }
        }

        echo "✅ Zaimportowano $count postaci w pełnym formacie.\n";
    }
}
