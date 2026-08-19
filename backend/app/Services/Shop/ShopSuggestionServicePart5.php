<?php

namespace App\Services\Shop;

use App\Models\ShopCatalogNodeModel;
use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopItemInstanceModel;
use App\Models\ShopProfileModel;
use App\Models\ShopSuggestionCacheModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopTypeModel;

trait ShopSuggestionServicePart5
{
    private function typeRuleCatalog(): array
    {
        return [
            'general' => [
                'id' => 'general',
                'label' => 'handel ogolny',
                'aliases' => ['general', 'general_stall', 'kram', 'stragan', 'targowisko', 'plac_targowy', 'sklad_towarow', 'dom_kupiecki', 'kramarz_wedrowny', 'kupiec_wedrowny'],
                'primaryClasses' => ['TOOL', 'FOOD', 'CUTLERY', 'STATIONERY', 'MISC', 'GADGET'],
                'secondaryClasses' => ['ALCHEMY', 'CLOTH'],
                'preferredGenres' => ['UTILITY', 'MEALS', 'DRINKS', 'BAKERY', 'PRESERVES'],
                'terms' => ['kram', 'codzien', 'lina', 'swiec', 'igla', 'sznur', 'kubek', 'sol', 'chleb', 'krzesiwo', 'worek'],
                'seeds' => [
                    $this->seed('Lina konopna', 'Zwoj mocnej liny do pakowania, wspinaczki i pracy przy wozie.', 'TOOL', 'UTILITY', 90, 35, 'equipment', 'v1030'),
                    $this->seed('Swieca lojowa', 'Prosta swieca do latarni, piwnicy albo nocnego dyzuru.', 'TOOL', 'UTILITY', 12, 3, 'products', 'v1030', 3),
                    $this->seed('Krzesiwo i hubka', 'Podstawowy zestaw do rozpalania ognia w drodze.', 'TOOL', 'UTILITY', 36, 6, 'equipment', 'v1030'),
                    $this->seed('Igly z nicia', 'Maly pakiet naprawczy do ubrania, sakwy i plaszcza.', 'TOOL', 'UTILITY', 18, 2, 'products', 'v1030'),
                    $this->seed('Kubek gliniany', 'Tani kubek z wypalonej gliny dla podroznych i robotnikow.', 'CUTLERY', 'UTILITY', 10, 4, 'products', 'v1148', 2),
                    $this->seed('Sol w woreczku', 'Niewielki woreczek soli do kuchni i konserwowania zywnosci.', 'FOOD', 'PRESERVES', 24, 3, 'products', 'v1148'),
                    $this->seed('Noz gospodarski', 'Prosty noz do ciecia sznurka, jedzenia i drobnych prac.', 'TOOL', 'UTILITY', 48, 10, 'equipment', 'v0170'),
                    $this->seed('Lampka oliwna', 'Mala lampka z taniego metalu, sprzedawana bez oliwy.', 'TOOL', 'UTILITY', 72, 12, 'equipment', 'v1030'),
                ],
            ],
            'medicine' => [
                'id' => 'medicine',
                'label' => 'apteka i alchemia',
                'aliases' => ['apothecary', 'aptekarz', 'zielarz', 'alchemik', 'alchemist', 'sklad_alchemiczny', 'cyrulik', 'truciciel', 'kadzielnik', 'pachnidlarz'],
                'primaryClasses' => ['ALCHEMY', 'POTION'],
                'secondaryClasses' => ['TOOL', 'FOOD'],
                'preferredGenres' => ['POTION', 'HEALING', 'BUFFS', 'TOXINS', 'SPICES_HERBS', 'UTILITY'],
                'terms' => ['eliksir', 'mikstur', 'masc', 'ziol', 'opatr', 'nalew', 'prosz', 'fiolk', 'leczen', 'truciz', 'odtrut'],
                'seeds' => [
                    $this->seed('Mikstura leczenia', 'Podstawowa mikstura przyspieszajaca gojenie ran.', 'POTION', 'HEALING', 240, 8, 'products', 'v1089'),
                    $this->seed('Masc nagietkowa', 'Gesty balsam na oparzenia, otarcia i popekana skore.', 'ALCHEMY', 'HEALING', 80, 4, 'products', 'v1089'),
                    $this->seed('Bandaz lniany', 'Czysty pas lnu do opatrywania ran po walce.', 'TOOL', 'UTILITY', 30, 2, 'equipment', 'v1030', 2),
                    $this->seed('Fiolka odtrutki', 'Gorzki plyn oslabiajacy dzialanie prostych trucizn.', 'POTION', 'HEALING', 520, 4, 'products', 'v1089'),
                    $this->seed('Susz z krwawnika', 'Suszone ziolo na napary przeciwzapalne i oklady.', 'ALCHEMY', 'HEALING', 42, 2, 'ingredients', 'v1089'),
                    $this->seed('Proszek przeciwgoraczkowy', 'Mieszanka sproszkowanych ziol na febre i dreszcze.', 'ALCHEMY', 'HEALING', 110, 2, 'products', 'v1089'),
                    $this->seed('Szklane fiolki', 'Komplet malych fiolek z korkiem do nalewek i probek.', 'TOOL', 'UTILITY', 75, 8, 'equipment', 'v1030'),
                    $this->seed('Pijawki lekarskie', 'Sloik pijawek uzywanych przez cyrulikow.', 'ALCHEMY', 'HEALING', 65, 3, 'ingredients', 'v1089'),
                ],
            ],
            'armorer' => [
                'id' => 'armorer',
                'label' => 'bron i pancerze',
                'aliases' => ['armorer', 'platnerz', 'zbrojownia', 'miecznik', 'kusznik', 'luczarz', 'rusznikarz', 'prochownia', 'ostrzarnia', 'drzewcownik', 'tarczownik', 'sklad_najemny', 'warsztat_bitewny', 'blacksmith', 'kowal'],
                'primaryClasses' => ['ARMOR', 'WEAPON', 'ARMAMENT'],
                'secondaryClasses' => ['TOOL'],
                'preferredGenres' => ['BODY', 'MELEE', 'RANGED', 'SHIELD', 'UTILITY'],
                'terms' => ['zbroj', 'pancer', 'helm', 'miecz', 'kusz', 'tarc', 'grot', 'belt', 'ostrze', 'napraw', 'kolcz'],
                'seeds' => [
                    $this->seed('Zbroja skorzana', 'Lekki pancerz z utwardzanej skory po naprawie warsztatowej.', 'ARMOR', 'BODY', 520, 80, 'products', 'v0619'),
                    $this->seed('Helm zelazny', 'Prosty helm z policzkami i paskiem pod brode.', 'ARMOR', 'HEAD', 360, 45, 'products', 'v0619'),
                    $this->seed('Tarcza okuta', 'Drewniana tarcza ze stalowym rantem i wymienionym uchwytem.', 'ARMOR', 'SHIELD', 300, 70, 'products', 'v0619'),
                    $this->seed('Naramienniki stalowe', 'Para naramiennikow do kolczugi albo pancerza skorzanego.', 'ARMOR', 'BODY', 420, 55, 'products', 'v0619'),
                    $this->seed('Zestaw nitow platnerskich', 'Nity, paski i male okucia do naprawy pancerza.', 'TOOL', 'UTILITY', 90, 12, 'equipment', 'v1030'),
                    $this->seed('Pas do miecza', 'Wzmocniony pas z pochwa i metalowa klamra.', 'TOOL', 'UTILITY', 130, 15, 'equipment', 'v1030'),
                    $this->seed('Ostrze zapasowe', 'Nieoprawione ostrze do krotkiego miecza lub kordelasa.', 'WEAPON', 'MELEE', 420, 35, 'products', 'v0170'),
                    $this->seed('Belty kusznicze', 'Wiazka beltow do kuszy lekkiej, z prostymi grotami.', 'WEAPON', 'RANGED', 96, 20, 'products', 'v0170', 2),
                ],
            ],
            'food' => [
                'id' => 'food',
                'label' => 'zywnosc i napitek',
                'aliases' => ['karczma', 'zajazd', 'oberza', 'gospoda', 'szynk', 'tawerna', 'jadlodajnia', 'piwiarnia', 'winiarnia', 'miodosytnia', 'piekarnia', 'mlynarz', 'jatka', 'wedzarnia', 'rybarnia', 'serownia', 'warzywnik', 'korzennik', 'sklad_win', 'piwnica_piwna'],
                'primaryClasses' => ['FOOD', 'CUTLERY'],
                'secondaryClasses' => ['TOOL'],
                'preferredGenres' => ['DRINKS', 'MEALS', 'BAKERY', 'PRESERVES', 'SPICES_HERBS', 'UTILITY'],
                'terms' => ['chleb', 'piwo', 'wino', 'miod', 'ser', 'mieso', 'ryb', 'kasz', 'sol', 'wedz', 'korzen'],
                'seeds' => [
                    $this->seed('Chleb razowy', 'Bochen ciemnego chleba na droge albo do wspolnej sali.', 'FOOD', 'BAKERY', 8, 3, 'products', 'v1148', 3),
                    $this->seed('Ser wedzony', 'Kawal twardego sera, dobry do przechowywania.', 'FOOD', 'PRESERVES', 26, 4, 'products', 'v1148'),
                    $this->seed('Suszone mieso', 'Porcja miesa suszonego nad dymem i sola.', 'FOOD', 'PRESERVES', 36, 3, 'products', 'v1148'),
                    $this->seed('Buklak piwa', 'Skorzany buklak napelniony prostym piwem.', 'FOOD', 'DRINKS', 18, 6, 'products', 'v1148'),
                    $this->seed('Garnek gulaszu', 'Ciezki garnek z goracym gulaszem dla kilku osob.', 'FOOD', 'MEALS', 54, 12, 'products', 'v1148'),
                    $this->seed('Przyprawy korzenne', 'Mala sakiewka ostrej mieszanki do mies i zup.', 'FOOD', 'SPICES_HERBS', 90, 2, 'products', 'v1148'),
                ],
            ],
            'travel' => [
                'id' => 'travel',
                'label' => 'podroz i wyprawa',
                'aliases' => ['sklad_podrozny', 'sklad_proviantu', 'rymarz', 'siodlarz', 'kaletnik', 'stajnia', 'stajnia_handlowa', 'stajnia_zajezdna', 'sklad_paszy', 'wozownia', 'kolodziej', 'powroznik', 'traper', 'mysliwski_kram'],
                'primaryClasses' => ['TOOL', 'FOOD', 'ANIMAL', 'FORAGE'],
                'secondaryClasses' => ['WEAPON', 'CLOTH'],
                'preferredGenres' => ['UTILITY', 'PRESERVES', 'RANGED', 'MEALS'],
                'terms' => ['podroz', 'siodl', 'uprzaz', 'owies', 'siano', 'woz', 'kolo', 'lina', 'sidla', 'pasza'],
                'seeds' => [
                    $this->seed('Koc podrozny', 'Gruby koc z welny do noclegu pod dachem albo pod wozem.', 'CLOTH', 'UTILITY', 80, 20, 'equipment', 'v1030'),
                    $this->seed('Buklak skorzany', 'Buklak z paskiem, uszczelniony woskiem.', 'TOOL', 'UTILITY', 60, 8, 'equipment', 'v1030'),
                    $this->seed('Hak zelazny', 'Hak do liny, ladunku i prostych prac przy wozie.', 'TOOL', 'UTILITY', 70, 12, 'equipment', 'v1030'),
                    $this->seed('Pochodnia smolna', 'Pochodnia gotowa do drogi przez las lub podziemia.', 'TOOL', 'UTILITY', 6, 5, 'equipment', 'v1030', 4),
                    $this->seed('Owies w worku', 'Worek owsa dla konia lub mula.', 'FOOD', 'FORAGE', 34, 20, 'products', 'v1148'),
                    $this->seed('Sidla mysliwskie', 'Prosty zestaw sidel na drobna zwierzyne.', 'TOOL', 'UTILITY', 95, 8, 'equipment', 'v1030'),
                ],
            ],
            'craft' => [
                'id' => 'craft',
                'label' => 'rzemioslo i narzedzia',
                'aliases' => ['zelaznik', 'garncarz', 'bednarz', 'ciesla', 'stolarz', 'koszykarz', 'swiecarz', 'mydlarz', 'szklarz', 'blacharz', 'nozownik'],
                'primaryClasses' => ['TOOL', 'GADGET'],
                'secondaryClasses' => ['STATIONERY', 'CUTLERY'],
                'preferredGenres' => ['UTILITY'],
                'terms' => ['narzedz', 'gwozd', 'zawias', 'mlot', 'dlut', 'deska', 'garnek', 'beczk', 'swiec', 'szklo'],
                'seeds' => [
                    $this->seed('Mlotek ciesielski', 'Nieduzy mlotek do gwozdzi i klinow.', 'TOOL', 'UTILITY', 120, 18, 'equipment', 'v1030'),
                    $this->seed('Gwozdzie zelazne', 'Garsc gwozdzi do skrzyn, drzwi i napraw wozu.', 'TOOL', 'UTILITY', 24, 8, 'products', 'v1030', 2),
                    $this->seed('Zawias kuty', 'Prosty zawias do skrzyni lub drzwi.', 'TOOL', 'UTILITY', 70, 10, 'products', 'v1030'),
                    $this->seed('Garnek gliniany', 'Wypalony garnek do kuchni i zapasow.', 'CUTLERY', 'UTILITY', 42, 12, 'products', 'v1148'),
                    $this->seed('Szare mydlo', 'Kostka ostrego mydla z loju i lugu.', 'TOOL', 'UTILITY', 18, 2, 'products', 'v1030', 2),
                    $this->seed('Olej do narzedzi', 'Mala butelka oleju chroniacego metal przed rdza.', 'TOOL', 'UTILITY', 58, 3, 'products', 'v1030'),
                ],
            ],
            'documents' => [
                'id' => 'documents',
                'label' => 'ksiegi i dokumenty',
                'aliases' => ['ksiegarz', 'skryptorium', 'pergamennik', 'inkaustnik', 'kartograf', 'pieczetarz', 'drukarz', 'antykwariusz'],
                'primaryClasses' => ['STATIONERY', 'MISC'],
                'secondaryClasses' => ['TOOL', 'GADGET'],
                'preferredGenres' => ['UTILITY'],
                'terms' => ['ksieg', 'pergamin', 'papier', 'atrament', 'pioro', 'map', 'lak', 'pieczec'],
                'seeds' => [
                    $this->seed('Pergamin kupiecki', 'Arkusz pergaminu do umow, listow i rachunkow.', 'STATIONERY', 'UTILITY', 70, 1, 'products', 'v0724'),
                    $this->seed('Atrament czarny', 'Mala butelka atramentu dla skryby.', 'STATIONERY', 'UTILITY', 55, 2, 'products', 'v0724'),
                    $this->seed('Pioro gesie', 'Przyciete pioro gotowe do pisania.', 'STATIONERY', 'UTILITY', 14, 1, 'products', 'v0724', 2),
                    $this->seed('Mapa traktu', 'Szkic lokalnej drogi z karczmami i mostami.', 'STATIONERY', 'UTILITY', 180, 1, 'products', 'v0724'),
                    $this->seed('Lak pieczetny', 'Paleczka czerwonego laku do zabezpieczania listow.', 'STATIONERY', 'UTILITY', 48, 1, 'products', 'v0724'),
                ],
            ],
            'luxury' => [
                'id' => 'luxury',
                'label' => 'luksus i kosztownosci',
                'aliases' => ['jubiler', 'zlotnik', 'srebrnik', 'kamieniarz_szlachetny', 'jedwabnik', 'lustrzarz', 'szkatulkarz', 'zegarmistrz'],
                'primaryClasses' => ['JEWELLERY', 'GADGET', 'CLOTH'],
                'secondaryClasses' => ['STATIONERY', 'TOOL'],
                'preferredGenres' => ['UTILITY'],
                'terms' => ['piersc', 'srebr', 'zloto', 'klejnot', 'jedwab', 'lustr', 'zeg', 'szkatul', 'ozdob'],
                'seeds' => [
                    $this->seed('Pierscien srebrny', 'Prosty srebrny pierscien z miejscem na grawer.', 'JEWELLERY', 'UTILITY', 620, 1, 'products', 'v1041'),
                    $this->seed('Brosza miedziana', 'Ozdobna brosza z motywem liscia.', 'JEWELLERY', 'UTILITY', 220, 1, 'products', 'v1041'),
                    $this->seed('Jedwabna wstazka', 'Cienka wstazka z drogiego materialu.', 'CLOTH', 'UTILITY', 140, 1, 'products', 'v1030'),
                    $this->seed('Puzderko lakowane', 'Male pudelko na bizuterie lub pieczec.', 'GADGET', 'UTILITY', 360, 3, 'products', 'v1041'),
                    $this->seed('Lusterko polerowane', 'Male lusterko w metalowej oprawie.', 'GADGET', 'UTILITY', 480, 2, 'products', 'v1041'),
                ],
            ],
            'black_market' => [
                'id' => 'black_market',
                'label' => 'czarny rynek',
                'aliases' => ['mordownia', 'melina', 'lombard', 'paserska_komora', 'cichy_kram', 'sklad_kontrabandy', 'melina_handlowa', 'sklepik_bez_znakow', 'trucicielska_szuflada', 'kram_szczurzy', 'zaulek_fantow', 'piwnica_dluznika', 'szafa_przemytnika'],
                'primaryClasses' => ['TOOL', 'WEAPON', 'ALCHEMY', 'GADGET'],
                'secondaryClasses' => ['JEWELLERY', 'CLOTH', 'STATIONERY'],
                'preferredGenres' => ['UTILITY', 'MELEE', 'TOXINS'],
                'terms' => ['wytrych', 'falsz', 'truciz', 'przemyt', 'noz', 'kaptur', 'lombard', 'zastaw', 'bez znakow'],
                'seeds' => [
                    $this->seed('Wytrychy proste', 'Zestaw tanich wytrychow w skorzanym rulonie.', 'TOOL', 'UTILITY', 260, 3, 'equipment', 'v1030'),
                    $this->seed('Noz bez znakow', 'Krotkie ostrze bez znaku warsztatu.', 'WEAPON', 'MELEE', 180, 8, 'products', 'v0170'),
                    $this->seed('Fiolka trucizny', 'Ciemna fiolka z lepka substancja bez etykiety.', 'ALCHEMY', 'TOXINS', 720, 2, 'products', 'v1089'),
                    $this->seed('Falszywa pieczec', 'Niedokladna kopia pieczeci kupieckiej.', 'STATIONERY', 'UTILITY', 540, 1, 'products', 'v0724'),
                    $this->seed('Kaptur bez znakow', 'Ciemny kaptur z grubego plotna.', 'CLOTH', 'UTILITY', 90, 3, 'products', 'v1030'),
                ],
            ],
            'religion' => [
                'id' => 'religion',
                'label' => 'kult i rytualy',
                'aliases' => ['dewocjonalia', 'sklad_swiatynny', 'relikwiarz', 'ikonnik', 'grabarz', 'kamieniarz_nagrobny'],
                'primaryClasses' => ['MISC', 'STATIONERY', 'TOOL'],
                'secondaryClasses' => ['JEWELLERY', 'ALCHEMY'],
                'preferredGenres' => ['UTILITY'],
                'terms' => ['swiec', 'kadzid', 'medalik', 'olej', 'modlit', 'relikw', 'ikona', 'trumna'],
                'seeds' => [
                    $this->seed('Swieca woskowa', 'Czysta swieca do modlitwy i nocnego czuwania.', 'TOOL', 'UTILITY', 32, 2, 'products', 'v1030', 2),
                    $this->seed('Kadzidlo zywiczne', 'Porcja kadzidla do rytualu i poswiecenia izby.', 'ALCHEMY', 'UTILITY', 95, 1, 'products', 'v1089'),
                    $this->seed('Medalik pielgrzyma', 'Prosty medalik z wybitym symbolem kultu.', 'JEWELLERY', 'UTILITY', 120, 1, 'products', 'v1041'),
                    $this->seed('Modlitewnik kieszonkowy', 'Niewielki zbior modlitw w taniej oprawie.', 'STATIONERY', 'UTILITY', 150, 1, 'products', 'v0724'),
                    $this->seed('Olej rytualny', 'Mala butelka oleju do namaszczen.', 'ALCHEMY', 'UTILITY', 180, 1, 'products', 'v1089'),
                ],
            ],
        ];
    }

    private function seed(string $name, string $description, string $class, string $genre, int $price, int $charge, string $segment, string $imgClass, int $quantity = 1): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'class' => $class,
            'genre' => $genre,
            'price' => $price,
            'charge' => $charge,
            'segment' => $segment,
            'imgClass' => $imgClass,
            'quantity' => $quantity,
        ];
    }

    private function profileTypeId(?array $profile): string
    {
        return (string) ($profile['type_id'] ?? $profile['typeId'] ?? '');
    }

    private function normalizeText(string $value): string
    {
        $value = strtr($value, [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ż' => 'z', 'ź' => 'z',
            'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'O', 'Ś' => 'S', 'Ż' => 'Z', 'Ź' => 'Z',
        ]);
        return strtolower($value);
    }

    private function normalizeKey(string $value): string
    {
        $value = $this->normalizeText($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
        return trim($value, '_');
    }

    private function templateItemClass(array $template): string
    {
        return strtoupper((string) ($template['item_class'] ?? $template['ITEM_CLASS'] ?? 'TOOL'));
    }

    private function templateItemGenre(array $template): string
    {
        return strtoupper((string) ($template['item_genre'] ?? $template['ITEM_GENRE'] ?? 'UTILITY'));
    }
}
