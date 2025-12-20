<?php

namespace App\Database\Seeds\Data;

/**
 * Dane startowe dla CoC 7e w settingu lat 20. XX w.
 *
 * Uwaga: opisy/listy (poza przekazana przez uzytkownika lista umiejetnosci) sa "homebrew"
 * i maja sluzyc jako komplet startowy do testowania aplikacji.
 */
class Coc7e1920sData
{
    /**
     * Format: [id, name, base_percent, flags]
     * flags: ['context_only' => bool, 'specializable' => bool, 'not_on_sheet' => bool]
     */
    public static function getSkills(): array
    {
        return [
            [1, 'Aktorstwo', 5, ['not_on_sheet' => true, 'specializable' => true]],
            [2, 'Antropologia', 1, []],
            [3, 'Archeologia', 1, []],
            [4, 'Astronomia', 1, ['not_on_sheet' => true, 'specializable' => true]],
            [5, 'Bicz', 5, ['specializable' => true]],
            [6, 'Bijatyka', 25, ['specializable' => true]],
            [7, 'Biologia', 1, ['specializable' => true]],
            [8, 'Botanika', 1, ['specializable' => true]],
            [9, 'Bron ciezka', 1, ['not_on_sheet' => true, 'specializable' => true]],
            [10, 'Bron krotka', 20, ['specializable' => true]],
            [11, 'Bron obuchowa', 10, ['specializable' => true]],
            [12, 'Charakteryzacja', 5, []],
            [13, 'Chemia', 1, ['not_on_sheet' => true, 'specializable' => true]],
            [14, 'Czytanie z ruchu warg', 1, ['not_on_sheet' => true]],
            [15, 'Elektronika', 1, ['not_on_sheet' => true]],
            [16, 'Elektryka', 10, ['not_on_sheet' => true]],
            [17, 'Falszerstwo', 5, ['specializable' => true]],
            [18, 'Farmacja', 1, ['not_on_sheet' => true, 'specializable' => true]],
            [19, 'Fizyka', 1, ['not_on_sheet' => true, 'specializable' => true]],
            [20, 'Fotografia', 5, ['specializable' => true]],
            [21, 'Gadanina', 5, []],
            [22, 'Garota', 15, ['not_on_sheet' => true, 'specializable' => true]],
            [23, 'Geologia', 1, ['not_on_sheet' => true, 'specializable' => true]],
            [24, 'Hipnoza', 1, ['not_on_sheet' => true]],
            [25, 'Historia', 5, []],
            [26, 'Inzynieria', 1, ['not_on_sheet' => true, 'specializable' => true]],
            [27, 'Jezdziectwo', 5, []],
            [28, 'Jezyk (obcy)', 1, ['specializable' => true]],
            [29, 'Jezyk (ojczysty)', 0, ['context_only' => true]],
            [30, 'Karabin', 25, ['not_on_sheet' => true, 'specializable' => true]],
            [31, 'Karabin maszynowy', 10, ['specializable' => true]],
            [32, 'Korzystanie z biblioteki', 20, []],
            [33, 'Korzystanie z komputerow', 5, ['not_on_sheet' => true]],
            [34, 'Kryminalistyka', 1, ['not_on_sheet' => true, 'specializable' => true]],
            [35, 'Kryptografia', 1, ['not_on_sheet' => true, 'specializable' => true]],
            [36, 'Ksiegowosc', 5, []],
            [37, 'Luk', 15, ['not_on_sheet' => true, 'specializable' => true]],
            [38, 'Majetnosc', 0, []],
            [39, 'Matematyka', 10, ['not_on_sheet' => true, 'specializable' => true]],
            [40, 'Materialy wybuchowe', 1, ['not_on_sheet' => true]],
            [41, 'Mechanika', 10, []],
            [42, 'Medycyna', 1, []],
            [43, 'Meteorologia', 1, ['not_on_sheet' => true, 'specializable' => true]],
            [44, 'Miecz', 20, ['not_on_sheet' => true, 'specializable' => true]],
            [45, 'Miotacz ognia', 10, ['not_on_sheet' => true, 'specializable' => true]],
            [46, 'Mity Cthulhu', 0, []],
            [47, 'Nasluchiwanie', 20, []],
            [48, 'Nauka', 1, ['specializable' => true]],
            [49, 'Nawigacja', 10, []],
            [50, 'Nurkowanie', 1, ['not_on_sheet' => true]],
            [51, 'Obsluga ciezkiego sprzetu', 1, ['not_on_sheet' => true]],
            [52, 'Okultyzm', 5, []],
            [53, 'Perswazja', 10, []],
            [54, 'Pierwsza pomoc', 30, []],
            [55, 'Pilotowanie', 1, ['not_on_sheet' => true]],
            [56, 'Pila lancuchowa', 10, ['not_on_sheet' => true, 'specializable' => true]],
            [57, 'Pistolet maszynowy', 15, ['not_on_sheet' => true, 'specializable' => true]],
            [58, 'Plywanie', 20, []],
            [59, 'Prawo', 5, []],
            [60, 'Prowadzenie samochodu', 20, []],
            [61, 'Psychoanaliza', 1, []],
            [62, 'Psychologia', 10, []],
            [63, 'Rzucanie', 20, []],
            [64, 'Skakanie', 20, []],
            [65, 'Spostrzegawczosc', 25, []],
            [66, 'Strzelba', 25, ['not_on_sheet' => true, 'specializable' => true]],
            [67, 'Sztuka przetrwania', 10, ['not_on_sheet' => true]],
            [68, 'Sztuka/Rzemioslo', 5, ['specializable' => true]],
            [69, 'Sztuki piekne', 5, ['not_on_sheet' => true, 'specializable' => true]],
            [70, 'Slusarstwo', 1, ['not_on_sheet' => true]],
            [71, 'Topor/Siekiera', 15, ['not_on_sheet' => true, 'specializable' => true]],
            [72, 'Tresura zwierzat', 5, ['not_on_sheet' => true]],
            [73, 'Tropienie', 10, []],
            [74, 'Ukrywanie', 20, []],
            [75, 'Unik', 0, ['context_only' => true]],
            [76, 'Urok osobisty', 15, []],
            [77, 'Walka wrecz', 0, ['specializable' => true, 'context_only' => true]],
            [78, 'Wiedza o naturze', 10, []],
            [79, 'Wiedza tajemna', 1, ['not_on_sheet' => true, 'specializable' => true]],
            [80, 'Wlocznia', 20, ['not_on_sheet' => true, 'specializable' => true]],
            [81, 'Wspinaczka', 20, []],
            [82, 'Wycena', 5, []],
            [83, 'Zastraszanie', 15, []],
            [84, 'Zoologia', 1, ['not_on_sheet' => true, 'specializable' => true]],
            [85, 'Zreczne palce', 10, []],
        ];
    }

    /**
     * Format: [id, code, name, short, description]
     */
    public static function getAttributes(): array
    {
        return [
            [1, 'STR', 'Sila', 'STR', 'Teczka cecha fizyczna opisujaca potege miesni.'],
            [2, 'CON', 'Kondycja', 'CON', 'Odporność organizmu, zdrowie i wytrzymalosc.'],
            [3, 'SIZ', 'Budowa', 'SIZ', 'Rozmiar i masa ciala, wzrost/postawe.'],
            [4, 'DEX', 'Zrecznosc', 'DEX', 'Koordynacja, refleks i precyzja ruchow.'],
            [5, 'APP', 'Aparycja', 'APP', 'Wrazenie jakie robisz na innych, atrakcyjnosc.'],
            [6, 'INT', 'Inteligencja', 'INT', 'Zdolnosc kojarzenia faktow i rozumowania.'],
            [7, 'POW', 'Moc', 'POW', 'Sila woli i wewnetrzna determinacja.'],
            [8, 'EDU', 'Wyksztalcenie', 'EDU', 'Zakres wiedzy i formalnego przygotowania.'],
            [9, 'LUCK', 'Szczescie', 'LUCK', 'Fart, zbieg okolicznosci, ochrona losu.'],
            [10, 'SAN', 'Poczytalnosc', 'SAN', 'Stabilnosc psychiczna i odporność na groze.'],
        ];
    }

    /**
     * Homebrew lista profesji/zajec (occupations).
     * Format: [id, name, description, suggested_skills]
     */
    public static function getOccupations(): array
    {
        return [
            [1, 'Dziennikarz', 'Reporter lub publicysta szukajacy prawdy i sensacji.', ['Gadanina', 'Perswazja', 'Psychologia', 'Korzystanie z biblioteki', 'Fotografia', 'Spostrzegawczosc']],
            [2, 'Detektyw prywatny', 'Prywatny sledczy rozwiazuje sprawy, ktorych nie chce policja.', ['Kryminalistyka', 'Spostrzegawczosc', 'Psychologia', 'Nasluchiwanie', 'Prowadzenie samochodu', 'Prawo']],
            [3, 'Archeolog', 'Badacz starozytnosci, czesto trafia na rzeczy, ktorych lepiej nie budzic.', ['Archeologia', 'Historia', 'Nauka', 'Sztuka przetrwania', 'Jezyk (obcy)', 'Wycena']],
            [4, 'Lekarz', 'Specjalista od ran i chorob; w horrorze bywa pierwsza linia obrony.', ['Medycyna', 'Pierwsza pomoc', 'Psychologia', 'Biologia', 'Farmacja', 'Perswazja']],
            [5, 'Prawnik', 'Zna przepisy i potrafi je naginac lub egzekwowac.', ['Prawo', 'Perswazja', 'Gadanina', 'Psychologia', 'Ksiegowosc', 'Korzystanie z biblioteki']],
            [6, 'Profesor', 'Naukowiec/wykladowca, laczy kropki i szuka dowodow.', ['Nauka', 'Korzystanie z biblioteki', 'Jezyk (obcy)', 'Historia', 'Psychologia', 'Okultyzm']],
            [7, 'Policjant', 'Straznik porzadku, ktory za duzo widzial.', ['Spostrzegawczosc', 'Nasluchiwanie', 'Prawo', 'Prowadzenie samochodu', 'Zastraszanie', 'Bron krotka']],
            [8, 'Pilot', 'Lotnik, ktory potrafi dotrzec tam, gdzie nie ma drog.', ['Pilotowanie', 'Nawigacja', 'Mechanika', 'Nasluchiwanie', 'Spostrzegawczosc', 'Zreczne palce']],
            [9, 'Dylentant', 'Osoba z pieniedzmi i kontaktami, ktora moze sobie pozwolic na ryzyko.', ['Urok osobisty', 'Perswazja', 'Wycena', 'Okultyzm', 'Jezyk (obcy)', 'Majetnosc']],
            [10, 'Gangster', 'Czlowiek z podziemia; grozny, ale bywa przydatny.', ['Zastraszanie', 'Ukrywanie', 'Bron krotka', 'Prowadzenie samochodu', 'Gadanina', 'Zreczne palce']],
        ];
    }

    /**
     * Format: [code, name, description]
     */
    public static function getItemClasses(): array
    {
        return [
            ['coc_weapon', 'Bron', 'Bron palna i biala, improwizowane narzedzia przemocy.'],
            ['coc_tool', 'Narzedzia', 'Wyposazenie przydatne w sledztwie i podrózy.'],
            ['coc_med', 'Medycyna', 'Apteczki i sprzet medyczny.'],
            ['coc_book', 'Ksiazki', 'Publikacje, wycinki, notatniki.'],
        ];
    }

    /**
     * Format: [id, name, description, slot, price, availability, code, metadata]
     * price/availability to wartosci umowne (1-100) na potrzeby aplikacji.
     */
    public static function getItems(): array
    {
        return [
            [1, 'Latarka', 'Prosta latarka na baterie.', null, 15, 80, 'flashlight', ['class_code' => 'coc_tool']],
            [2, 'Notatnik', 'Notes i olowek do zapisywania tropow.', null, 2, 95, 'notebook', ['class_code' => 'coc_tool']],
            [3, 'Aparat fotograficzny', 'Aparat z klisza; wymaga swiatla i czasu.', null, 60, 60, 'camera', ['class_code' => 'coc_tool']],
            [4, 'Apteczka', 'Podstawowy zestaw opatrunkow.', null, 25, 70, 'first_aid_kit', ['class_code' => 'coc_med']],
            [5, 'Rewolwer', 'Bron palna krotka, niezawodna.', null, 80, 40, 'revolver', ['class_code' => 'coc_weapon', 'tags' => ['firearm']]],
            [6, 'Strzelba', 'Bron palna dluga, skuteczna na blisko.', null, 90, 35, 'shotgun', ['class_code' => 'coc_weapon', 'tags' => ['firearm']]],
            [7, 'Noz kieszonkowy', 'Maly noz, przydatny tez jako narzedzie.', null, 10, 85, 'knife', ['class_code' => 'coc_weapon', 'tags' => ['melee']]],
            [8, 'Palka', 'Drewniana palka lub kij.', null, 5, 90, 'club', ['class_code' => 'coc_weapon', 'tags' => ['melee']]],
            [9, 'Zestaw wytrychow', 'Wytrychy i cienkie narzedzia do zamkow.', null, 35, 20, 'lockpicks', ['class_code' => 'coc_tool']],
            [10, 'Mapa miasta', 'Mapa okolicy, ułatwia orientacje.', null, 3, 90, 'city_map', ['class_code' => 'coc_tool']],
        ];
    }

    /**
     * Format: [id, bundle_type, name, description, items]
     * items: [['code' => '...', 'qty' => int], ...]
     */
    public static function getItemBundles(): array
    {
        return [
            [1, 'bg', 'Zestaw sledczego', 'Podstawowe wyposazenie do prowadzenia dochodzenia.', [
                ['code' => 'notebook', 'qty' => 1],
                ['code' => 'flashlight', 'qty' => 1],
                ['code' => 'city_map', 'qty' => 1],
            ]],
            [2, 'bg', 'Zestaw medyka', 'Wyposazenie do udzielania pomocy.', [
                ['code' => 'first_aid_kit', 'qty' => 1],
                ['code' => 'notebook', 'qty' => 1],
            ]],
            [3, 'opt', 'Zestaw obrony', 'Dyskretne srodki samoobrony.', [
                ['code' => 'knife', 'qty' => 1],
                ['code' => 'revolver', 'qty' => 1],
            ]],
        ];
    }

    /**
     * Homebrew: [id, name, description, type]
     */
    public static function getInsanities(): array
    {
        return [
            [1, 'Fobia: ciemnosc', 'Silny lek przed ciemnoscia i miejscami bez swiatla.', 'phobia'],
            [2, 'Fobia: owady', 'Natrętne obrzydzenie i panika na widok insektow.', 'phobia'],
            [3, 'Mania: zbieractwo', 'Przymus gromadzenia drobnych przedmiotow i pamiatek.', 'mania'],
            [4, 'Urojenia: sledzony', 'Przekonanie, ze ktos stale cie obserwuje.', 'delusion'],
            [5, 'Bezsenność', 'Trudnosci z zasypianiem, koszmary, zmeczenie.', 'condition'],
        ];
    }

    /**
     * Homebrew: [id, group, name, description, action_type]
     */
    public static function getCombatActions(): array
    {
        return [
            [1, 1, 'Atak', 'Proba zadania obrazen bronia lub piescia.', 'attack'],
            [2, 1, 'Unik', 'Uchyl sie przed ciosem lub strzalem, jesli masz czas na reakcje.', 'defense'],
            [3, 2, 'Celowanie', 'Skup sie na celu, aby zwiekszyc szanse trafienia.', 'utility'],
            [4, 2, 'Strzal', 'Oddaj strzal z broni palnej.', 'attack'],
            [5, 2, 'Ucieczka', 'Wycofaj sie z walki i szukaj oslon.', 'move'],
        ];
    }

    /**
     * Homebrew: [id, name, description]
     */
    public static function getWeaponTraits(): array
    {
        return [
            [1, 'Glosna', 'Oddanie strzalu zwraca uwage i zdradza pozycje.'],
            [2, 'Niezawodna', 'Rzadko zawodzi w krytycznym momencie.'],
            [3, 'Ciezka', 'Trudna do ukrycia i niewygodna w przenoszeniu.'],
        ];
    }
}
