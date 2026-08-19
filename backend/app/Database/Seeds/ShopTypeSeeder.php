<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ShopTypeSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        foreach ($this->shopTypes() as $index => $type) {
            $record = [
                'slug' => $type[0],
                'name' => $type[1],
                'category' => $type[2],
                'description' => $type[3],
                'is_active' => 1,
                'sort_order' => $index + 1,
            ];

            $existing = $db->table('shop_types')
                ->where('slug', $record['slug'])
                ->get()
                ->getRowArray();

            if ($existing) {
                $db->table('shop_types')
                    ->where('id', (int) $existing['id'])
                    ->update(array_merge($record, ['updated_at' => $now]));
                continue;
            }

            $db->table('shop_types')->insert(array_merge($record, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    private function shopTypes(): array
    {
        return [
            ['karczma', 'Karczma', 'Jadło, napitek i nocleg', 'Jadło, piwo, prosty nocleg, plotki, lokalni bywalcy, zlecenia.'],
            ['zajazd', 'Zajazd', 'Jadło, napitek i nocleg', 'Nocleg dla podróżnych, stajnia, jadło, zapasy, obsługa wozów i koni.'],
            ['oberza', 'Oberża', 'Jadło, napitek i nocleg', 'Lepsza karczma z pokojami, ciepłym posiłkiem i bezpieczniejszym standardem.'],
            ['gospoda', 'Gospoda', 'Jadło, napitek i nocleg', 'Lokalne jadło, napitek, wspólna sala, proste pokoje, miejsce spotkań mieszkańców.'],
            ['szynk', 'Szynk', 'Jadło, napitek i nocleg', 'Wyszynk alkoholu, piwo, tanie jadło, rozmowy, lokalni bywalcy.'],
            ['tawerna', 'Tawerna', 'Jadło, napitek i nocleg', 'Portowe jadło, alkohol, marynarze, rybacy, plotki, bójki i przemyt.'],
            ['mordownia', 'Mordownia', 'Jadło, napitek i nocleg', 'Tani alkohol, podejrzani klienci, bójki, półświatek, paserzy i najemnicy.'],
            ['melina', 'Melina', 'Jadło, napitek i nocleg', 'Nielegalny lub półlegalny wyszynk, kryjówka, szemrany handel i kontakty półświatka.'],
            ['jadlodajnia', 'Jadłodajnia', 'Jadło, napitek i nocleg', 'Tanie jedzenie, zupy, kasze, gulasze, prosta strawa dla biedoty i robotników.'],
            ['piwiarnia', 'Piwiarnia', 'Jadło, napitek i nocleg', 'Piwo, ale, beczki, kufle, lokalni bywalcy i rozmowy przy ławie.'],
            ['winiarnia', 'Winiarnia', 'Jadło, napitek i nocleg', 'Wino, droższe trunki, bogatsi mieszczanie, układy i spokojniejsze spotkania.'],
            ['miodosytnia', 'Miodosytnia', 'Jadło, napitek i nocleg', 'Miody pitne, miód, wosk, świece woskowe i słodkie trunki.'],
            ['dom_goscinny', 'Dom Gościnny', 'Jadło, napitek i nocleg', 'Spokojniejszy nocleg, pokoje, posiłki i obsługa podróżnych.'],
            ['dom_noclegowy', 'Dom Noclegowy', 'Jadło, napitek i nocleg', 'Tani nocleg, sienniki, wspólna sala, ciasne pokoje i podstawowe usługi.'],
            ['stancja', 'Stancja', 'Jadło, napitek i nocleg', 'Dłuższy pobyt, pokój do wynajęcia, lokum dla skrybów, uczniów, mieszczan i podróżnych.'],
            ['kuchnia_polowa', 'Kuchnia Polowa', 'Jadło, napitek i nocleg', 'Jadło dla żołnierzy, najemników, uchodźców, karawan lub obozowisk.'],
            ['kram', 'Kram', 'Handel ogólny i targ', 'Drobne towary codzienne, świece, igły, sznurki, kubki, noże i proste narzędzia.'],
            ['stragan', 'Stragan', 'Handel ogólny i targ', 'Żywność, owoce, warzywa, ryby, pieczywo, tanie rzeczy sezonowe.'],
            ['targowisko', 'Targowisko', 'Handel ogólny i targ', 'Wiele drobnych stoisk, przypadkowy asortyment, żywność, narzędzia, okazje.'],
            ['plac_targowy', 'Plac Targowy', 'Handel ogólny i targ', 'Większe targowisko z żywnością, zwierzętami, wozami, najemnikami i usługami.'],
            ['sklad_towarow', 'Skład Towarów', 'Handel ogólny i targ', 'Handel ogólny, skrzynie, beczki, płótno, sól, pasza, narzędzia i większe ilości towaru.'],
            ['dom_kupiecki', 'Dom Kupiecki', 'Handel ogólny i targ', 'Droższe towary, handel hurtowy, zamówienia, kontakty, import i towary lepszej jakości.'],
            ['kramarz_wedrowny', 'Kramarz Wędrowny', 'Handel ogólny i targ', 'Mobilny losowy sklep, drobne towary, plotki, okazje, egzotyka i towary z drogi.'],
            ['kupiec_wedrowny', 'Kupiec Wędrowny', 'Handel ogólny i targ', 'Towary z innych miast, przyprawy, tkaniny, droższe drobiazgi i zamówienia.'],
            ['kram_osobliwosci', 'Kram Osobliwości', 'Handel ogólny i targ', 'Dziwne przedmioty, pamiątki, relikty, podejrzane artefakty i kurioza.'],
            ['sklad_cechowy', 'Skład Cechowy', 'Handel ogólny i targ', 'Towary konkretnego cechu, materiały, narzędzia, zamówienia i wyroby rzemieślnicze.'],
            ['sklad_podrozny', 'Skład Podróżny', 'Podróż, wyprawa i zwierzęta', 'Plecaki, koce, manierki, liny, haki, namioty, krzesiwa i ekwipunek awanturnika.'],
            ['sklad_proviantu', 'Skład Proviantu', 'Podróż, wyprawa i zwierzęta', 'Suchary, kasza, suszone mięso, bukłaki, ser, sól i racje podróżne.'],
            ['rymarz', 'Rymarz', 'Podróż, wyprawa i zwierzęta', 'Siodła, uprzęże, juki, wodze, torby końskie, pasy i oporządzenie.'],
            ['siodlarz', 'Siodlarz', 'Podróż, wyprawa i zwierzęta', 'Siodła, strzemiona, czapraki, popręgi i naprawa oporządzenia jeździeckiego.'],
            ['kaletnik', 'Kaletnik', 'Podróż, wyprawa i zwierzęta', 'Sakwy, torby, mieszki, pochwy, futerały i paski.'],
            ['stajnia', 'Stajnia', 'Podróż, wyprawa i zwierzęta', 'Konie, pasza, opieka nad zwierzętami, wymiana lub przechowanie wierzchowców.'],
            ['stajnia_handlowa', 'Stajnia Handlowa', 'Podróż, wyprawa i zwierzęta', 'Konie, muły, osły, kucyki i zwierzęta pociągowe.'],
            ['stajnia_zajezdna', 'Stajnia Zajezdna', 'Podróż, wyprawa i zwierzęta', 'Konie podróżne, pasza, oporządzenie i obsługa przy zajeździe.'],
            ['sklad_paszy', 'Skład Paszy', 'Podróż, wyprawa i zwierzęta', 'Owies, siano, obrok, sól dla zwierząt i worki paszowe.'],
            ['wozownia', 'Wozownia', 'Podróż, wyprawa i zwierzęta', 'Wozy, wózki, sanie, koła, osie i części do pojazdów.'],
            ['kolodziej', 'Kołodziej', 'Podróż, wyprawa i zwierzęta', 'Koła, szprychy, obręcze i naprawa wozów.'],
            ['powroznik', 'Powroźnik', 'Podróż, wyprawa i zwierzęta', 'Liny, sznury, sieci, cumy i pasy mocujące.'],
            ['traper', 'Traper', 'Podróż, wyprawa i zwierzęta', 'Pułapki, sidła, skóry, noże myśliwskie, sieci i przynęty.'],
            ['mysliwski_kram', 'Myśliwski Kram', 'Podróż, wyprawa i zwierzęta', 'Łuki, oszczepy, sidła, rogi, wabiki, trofea i sprzęt łowiecki.'],
            ['zbrojownia', 'Zbrojownia', 'Broń, pancerze i wojna', 'Broń, pancerze, tarcze, hełmy i ekwipunek bojowy.'],
            ['platnerz', 'Płatnerz', 'Broń, pancerze i wojna', 'Zbroje skórzane, kolcze i płytowe, hełmy, naramienniki i naprawa pancerzy.'],
            ['miecznik', 'Miecznik', 'Broń, pancerze i wojna', 'Miecze, szable, kordy, rapiery, pochwy i pasy do broni.'],
            ['kusznik', 'Kusznik', 'Broń, pancerze i wojna', 'Kusze, bełty, mechanizmy naciągowe i oleje do konserwacji.'],
            ['luczarz', 'Łuczarz', 'Broń, pancerze i wojna', 'Łuki, strzały, kołczany i cięciwy.'],
            ['rusznikarz', 'Rusznikarz', 'Broń, pancerze i wojna', 'Pistolety, muszkiety, garłacze, prochownice, lonty i kule.'],
            ['prochownia', 'Prochownia', 'Broń, pancerze i wojna', 'Proch, kule, lonty, przybitki, prochownice i akcesoria do broni palnej.'],
            ['ostrzarnia', 'Ostrzarnia', 'Broń, pancerze i wojna', 'Ostrzenie mieczy, noży, toporów, naprawa kling i wymiana rękojeści.'],
            ['drzewcownik', 'Drzewcownik', 'Broń, pancerze i wojna', 'Włócznie, halabardy, glewie, piki i broń drzewcowa.'],
            ['tarczownik', 'Tarczownik', 'Broń, pancerze i wojna', 'Tarcze, puklerze, okucia tarcz i naprawa osłon.'],
            ['sklad_najemny', 'Skład Najemny', 'Broń, pancerze i wojna', 'Używana broń, pancerze, ekwipunek wojenny i wyposażenie najemników.'],
            ['warsztat_bitewny', 'Warsztat Bitewny', 'Broń, pancerze i wojna', 'Naprawa broni, pancerzy, tarcz i uprzęży wojennej.'],
            ['aptekarz', 'Aptekarz', 'Leczenie, alchemia i zioła', 'Leki, maści, nalewki, proszki, bandaże i środki przeciwbólowe.'],
            ['zielarz', 'Zielarz', 'Leczenie, alchemia i zioła', 'Zioła lecznicze, korzenie, suszone kwiaty, napary i zioła rytualne.'],
            ['alchemik', 'Alchemik', 'Leczenie, alchemia i zioła', 'Eliksiry, odczynniki, kwasy, sole, oleje i szkło alchemiczne.'],
            ['sklad_alchemiczny', 'Skład Alchemiczny', 'Leczenie, alchemia i zioła', 'Alembiki, retorty, fiolki, moździerze, szkło i substancje bazowe.'],
            ['cyrulik', 'Cyrulik', 'Leczenie, alchemia i zioła', 'Bandaże, pijawki, igły, noże zabiegowe, maści i usługi leczenia.'],
            ['truciciel', 'Truciciel', 'Leczenie, alchemia i zioła', 'Trucizny, odtrutki, usypiacze, jady i fiolki bez etykiet.'],
            ['kadzielnik', 'Kadzielnik', 'Leczenie, alchemia i zioła', 'Kadzidła, żywice, wonne mieszanki, mirra i zioła rytualne.'],
            ['pachnidlarz', 'Pachnidlarz', 'Leczenie, alchemia i zioła', 'Perfumy, olejki, mydła, pudry, wonności i drogie esencje.'],
            ['piekarnia', 'Piekarnia', 'Żywność i zapasy', 'Chleb, bułki, podpłomyki i suchary.'],
            ['mlynarz', 'Młynarz', 'Żywność i zapasy', 'Mąka, kasza, ziarno i otręby.'],
            ['jatka', 'Jatka', 'Żywność i zapasy', 'Mięso, kości, podroby, tłuszcz i tania żywność mięsna.'],
            ['kram_miesny', 'Kram Mięsny', 'Żywność i zapasy', 'Mięso, kiełbasy, słonina i podroby.'],
            ['wedzarnia', 'Wędzarnia', 'Żywność i zapasy', 'Wędzone mięsa, kiełbasy, ryby i sery.'],
            ['rybarnia', 'Rybarnia', 'Żywność i zapasy', 'Świeże ryby, solone ryby, suszone ryby i tran.'],
            ['serownia', 'Serownia', 'Żywność i zapasy', 'Sery, twarogi, masło i śmietana.'],
            ['warzywnik', 'Warzywnik', 'Żywność i zapasy', 'Warzywa, cebula, czosnek, kapusta, groch i podstawowa żywność.'],
            ['zielnik_jadalny', 'Zielnik Jadalny', 'Żywność i zapasy', 'Zioła kuchenne, przyprawy, suszone rośliny i dodatki do potraw.'],
            ['sklad_soli', 'Skład Soli', 'Żywność i zapasy', 'Sól, solone mięso, solone ryby i konserwowane zapasy.'],
            ['korzennik', 'Korzennik', 'Żywność i zapasy', 'Przyprawy, pieprz, goździki, cynamon, szafran i egzotyczne dodatki.'],
            ['sklad_win', 'Skład Win', 'Żywność i zapasy', 'Wino, octy, bukłaki, kielichy i dzbany.'],
            ['piwnica_piwna', 'Piwnica Piwna', 'Żywność i zapasy', 'Piwo, ale, beczki, kufle i dzbany.'],
            ['krawiec', 'Krawiec', 'Odzież, tkaniny i skóra', 'Ubrania, płaszcze, kaftany, koszule i poprawki.'],
            ['sukiennik', 'Sukiennik', 'Odzież, tkaniny i skóra', 'Sukno, bele materiału i tkaniny miejskie.'],
            ['lniarz', 'Lniarz', 'Odzież, tkaniny i skóra', 'Len, płótno, worki, bielizna i proste koszule.'],
            ['futrzarz', 'Futrzarz', 'Odzież, tkaniny i skóra', 'Futra, kożuchy, obszycia i skóry zimowe.'],
            ['kapelusznik', 'Kapelusznik', 'Odzież, tkaniny i skóra', 'Kapelusze, czapki, kaptury i berety.'],
            ['rekawicznik', 'Rękawicznik', 'Odzież, tkaniny i skóra', 'Rękawice, mitenki i skórzane osłony dłoni.'],
            ['pasamonik', 'Pasamonik', 'Odzież, tkaniny i skóra', 'Pasy ozdobne, taśmy, hafty, frędzle i guziki.'],
            ['szewc', 'Szewc', 'Odzież, tkaniny i skóra', 'Buty, trzewiki, sandały i naprawa obuwia.'],
            ['sklad_ubran_uzywanych', 'Skład Ubrań Używanych', 'Odzież, tkaniny i skóra', 'Tania odzież, łachy, płaszcze z drugiej ręki i rzeczy po poprzednich właścicielach.'],
            ['farbiarnia', 'Farbiarnia', 'Odzież, tkaniny i skóra', 'Barwniki, farbowane tkaniny i usługi farbowania.'],
            ['skornik', 'Skórnik', 'Odzież, tkaniny i skóra', 'Skóry, rzemienie, wyprawione płaty i skóry surowe.'],
            ['kowal', 'Kowal', 'Rzemiosło, dom i narzędzia', 'Narzędzia, gwoździe, podkowy, okucia i łańcuchy.'],
            ['zelaznik', 'Żelaznik', 'Rzemiosło, dom i narzędzia', 'Haki, klamry, zawiasy, kraty, pręty i okucia.'],
            ['garncarz', 'Garncarz', 'Rzemiosło, dom i narzędzia', 'Garnki, misy, kubki, dzbany i kafle.'],
            ['bednarz', 'Bednarz', 'Rzemiosło, dom i narzędzia', 'Beczki, wiadra, kadzie i cebry.'],
            ['ciesla', 'Cieśla', 'Rzemiosło, dom i narzędzia', 'Belki, deski, skrzynie i proste konstrukcje.'],
            ['stolarz', 'Stolarz', 'Rzemiosło, dom i narzędzia', 'Stoły, krzesła, kufry, szafy i szkatuły.'],
            ['koszykarz', 'Koszykarz', 'Rzemiosło, dom i narzędzia', 'Kosze, kobiałki i wiklinowe pojemniki.'],
            ['swiecarz', 'Świecarz', 'Rzemiosło, dom i narzędzia', 'Świece, łój, knoty i lampki.'],
            ['mydlarz', 'Mydlarz', 'Rzemiosło, dom i narzędzia', 'Mydła, ług, tłuszcze i proste pachnidła.'],
            ['szklarz', 'Szklarz', 'Rzemiosło, dom i narzędzia', 'Szyby, butelki, flakony i paciorki.'],
            ['blacharz', 'Blacharz', 'Rzemiosło, dom i narzędzia', 'Lampy, misy metalowe, pokrywki i blaszane naczynia.'],
            ['nozownik', 'Nożownik', 'Rzemiosło, dom i narzędzia', 'Noże, brzytwy, tasaki i igły metalowe.'],
            ['ksiegarz', 'Księgarz', 'Księgi, mapy i dokumenty', 'Księgi, kroniki, modlitewniki, poradniki i stare tomy.'],
            ['skryptorium', 'Skryptorium', 'Księgi, mapy i dokumenty', 'Przepisywanie ksiąg, dokumenty, kopie i listy.'],
            ['pergamennik', 'Pergamennik', 'Księgi, mapy i dokumenty', 'Pergamin, papier, zwoje, karty i oprawy.'],
            ['inkaustnik', 'Inkaustnik', 'Księgi, mapy i dokumenty', 'Atramenty, pióra, piasek do suszenia i kałamarze.'],
            ['kartograf', 'Kartograf', 'Księgi, mapy i dokumenty', 'Mapy, plany miast, mapy dróg i szkice ruin.'],
            ['pieczetarz', 'Pieczętarz', 'Księgi, mapy i dokumenty', 'Pieczęcie, laki, stemple, sygnety i znaki rodowe.'],
            ['drukarz', 'Drukarz', 'Księgi, mapy i dokumenty', 'Obwieszczenia, ulotki, broszury i tanie księgi.'],
            ['antykwariusz', 'Antykwariusz', 'Księgi, mapy i dokumenty', 'Stare księgi, relikty, pamiątki rodowe i dawne mapy.'],
            ['jubiler', 'Jubiler', 'Luksus i kosztowności', 'Pierścienie, naszyjniki, brosze i kolczyki.'],
            ['zlotnik', 'Złotnik', 'Luksus i kosztowności', 'Złote ozdoby, oprawy, kosztowności i insygnia.'],
            ['srebrnik', 'Srebrnik', 'Luksus i kosztowności', 'Srebrne naczynia, ozdoby, monety i medaliony.'],
            ['kamieniarz_szlachetny', 'Kamieniarz Szlachetny', 'Luksus i kosztowności', 'Klejnoty, oczka do pierścieni i kamienie ozdobne.'],
            ['jedwabnik', 'Jedwabnik', 'Luksus i kosztowności', 'Jedwab, koronki i luksusowe tkaniny.'],
            ['lustrzarz', 'Lustrzarz', 'Luksus i kosztowności', 'Lustra, polerowane metale i szkło ozdobne.'],
            ['szkatulkarz', 'Szkatułkarz', 'Luksus i kosztowności', 'Szkatuły, kasetki, puzderka i ozdobne pudełka.'],
            ['zegarmistrz', 'Zegarmistrz', 'Luksus i kosztowności', 'Zegary, mechanizmy, drobne sprężyny i kosztowne urządzenia.'],
            ['dewocjonalia', 'Dewocjonalia', 'Religia, kult i pogrzeby', 'Medaliki, symbole bogów, figurki, modlitewniki i drobne przedmioty kultu.'],
            ['sklad_swiatynny', 'Skład Świątynny', 'Religia, kult i pogrzeby', 'Świece, kadzidła, oleje, szaty kapłańskie i wyposażenie świątynne.'],
            ['relikwiarz', 'Relikwiarz', 'Religia, kult i pogrzeby', 'Relikwie, oprawy relikwii i święte pamiątki.'],
            ['ikonnik', 'Ikonnik', 'Religia, kult i pogrzeby', 'Ikony, obrazy świętych i tabliczki modlitewne.'],
            ['grabarz', 'Grabarz', 'Religia, kult i pogrzeby', 'Trumny, całuny, łopaty, znicze i usługi pogrzebowe.'],
            ['kamieniarz_nagrobny', 'Kamieniarz Nagrobny', 'Religia, kult i pogrzeby', 'Nagrobki, tablice, krzyże i inskrypcje.'],
            ['laznia', 'Łaźnia', 'Usługi miejskie i rozrywka', 'Kąpiel, golenie, odpoczynek, plotki i proste usługi cyrulika.'],
            ['dom_gry', 'Dom Gry', 'Usługi miejskie i rozrywka', 'Kości, karty, zakłady, hazard, długi i kontakty.'],
            ['dom_uciech', 'Dom Uciech', 'Usługi miejskie i rozrywka', 'Rozrywka, alkohol, muzyka, towarzystwo i plotki.'],
            ['kantor', 'Kantor', 'Finanse i zastaw', 'Wymiana monet, pożyczki, wycena kosztowności i weksle.'],
            ['lombard', 'Lombard', 'Finanse i zastaw', 'Zastawione przedmioty, używana broń, biżuteria, narzędzia i odzież.'],
            ['paserska_komora', 'Paserska Komora', 'Czarny rynek i półświatek', 'Kradzione towary, kosztowności, fanty bez pytań i rzeczy niewiadomego pochodzenia.'],
            ['cichy_kram', 'Cichy Kram', 'Czarny rynek i półświatek', 'Kontrabanda, drobne zakazane towary, podejrzane drobiazgi i sprzedaż spod lady.'],
            ['sklad_kontrabandy', 'Skład Kontrabandy', 'Czarny rynek i półświatek', 'Przemyt, broń, rzadkie towary, zakazane księgi i towary nielegalne.'],
            ['melina_handlowa', 'Melina Handlowa', 'Czarny rynek i półświatek', 'Używane wyposażenie, łupy, rzeczy po zmarłych i handel półświatka.'],
            ['sklepik_bez_znakow', 'Sklepik Bez Znaków', 'Czarny rynek i półświatek', 'Fałszywe dokumenty, przebrania, anonimowe przedmioty i towary bez pochodzenia.'],
            ['trucicielska_szuflada', 'Trucicielska Szuflada', 'Czarny rynek i półświatek', 'Trucizny, usypiacze, jady i fiolki bez etykiet.'],
            ['kram_szczurzy', 'Kram Szczurzy', 'Czarny rynek i półświatek', 'Tanie, brudne, podejrzane rzeczy z kanałów, zaułków i biedoty.'],
            ['zaulek_fantow', 'Zaułek Fantów', 'Czarny rynek i półświatek', 'Łupy, zastawy, dziwne okazje i przedmioty niewiadomego pochodzenia.'],
            ['piwnica_dluznika', 'Piwnica Dłużnika', 'Czarny rynek i półświatek', 'Zastawione przedmioty, broń, biżuteria, narzędzia i rzeczy po długach.'],
            ['szafa_przemytnika', 'Szafa Przemytnika', 'Czarny rynek i półświatek', 'Skrytki, fałszywe dna, torby i towary nielegalne.'],
        ];
    }
}
