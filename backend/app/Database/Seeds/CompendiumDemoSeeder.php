<?php

namespace App\Database\Seeds;

use App\Services\Compendium\CompendiumService;
use CodeIgniter\Database\Seeder;

/** Idempotent editorial sample for the first Old World installation. */
final class CompendiumDemoSeeder extends Seeder
{
    private $service;
    private $auth;
    private $universeId;
    private $tags = [];

    public function run()
    {
        if (!$this->db->tableExists('compendium_worlds')) {
            echo "Compendium tables are missing. Run migrations first.\n";
            return;
        }
        $universe = $this->db->table('rpg_universes')->where('code', 'old_world')->get()->getRowArray()
            ?: $this->db->table('rpg_universes')->orderBy('id')->get()->getRowArray();
        $user = $this->db->table('users')->orderBy('id')->get()->getRowArray();
        if (!$universe || !$user) {
            echo "Compendium demo requires at least one universe and one user.\n";
            return;
        }

        $this->universeId = (int) $universe['id'];
        $this->auth = ['user_id' => (int) $user['id'], 'role' => 'admin', 'anonymous' => false];
        $this->service = new CompendiumService($this->db);
        $overview = $this->service->overview($this->universeId, $this->auth);
        $calendar = $this->calendar($overview['calendar']);
        $types = array_column($overview['types'], 'id', 'code');
        $this->tags = $this->tags($overview['tags']);

        $region = $this->entry('demo-marchia-popielna', [
            'typeId' => $types['general'],
            'title' => 'Marchia Popielna',
            'aliases' => ['Północne Rubieże'],
            'excerpt' => 'Pogranicze dawnych traktów, wrzosowisk i ruin po wojnie magów.',
            'visibility' => 'players',
            'tagIds' => [$this->tags['region']],
            'publicContent' => $this->document(
                'Marchia Popielna leży między Górami Szeptów a zatopionym traktem królewskim.',
                'Kupcy podróżują tu wyłącznie w karawanach, a dym z samotnych wież ostrzegawczych widać przez wiele mil.'
            ),
            'gmContent' => $this->document('Pod ruinami traktu działa zapomniana sieć teleportacyjna.'),
        ]);

        $ford = $this->entry('demo-ksiezycowy-brod', [
            'typeId' => $types['place'],
            'parentEntryId' => $region,
            'title' => 'Księżycowy Bród',
            'aliases' => ['Srebrny Bród'],
            'excerpt' => 'Warowna osada zbudowana wokół jedynej bezpiecznej przeprawy przez rzekę Eren.',
            'visibility' => 'players',
            'tagIds' => [$this->tags['place'], $this->tags['adventure']],
            'publicContent' => $this->document(
                'Kamienie brodu świecą bladym światłem podczas pełni. Nad przeprawą góruje drewniana strażnica i targ pod płóciennymi dachami.',
                'Najważniejsze miejsca to Dom Przewoźnika, kaplica Trzech Dróg i zamknięta wieża poborcy.'
            ),
            'gmContent' => $this->document('Światło kamieni pochodzi z pieczęci więżącej ducha rzeki. Pęka ona podczas każdej nowiu.'),
            'relations' => [['targetEntryId' => $region, 'label' => 'Leży w', 'audience' => 'public']],
        ]);

        $wardens = $this->entry('demo-straznicy-popiolu', [
            'typeId' => $types['faction'],
            'title' => 'Strażnicy Popiołu',
            'excerpt' => 'Niewielki zakon strzegący dróg Marchii przed istotami z dawnych kurhanów.',
            'visibility' => 'players',
            'tagIds' => [$this->tags['faction']],
            'publicContent' => $this->document('Strażnicy noszą szare płaszcze spinane żelaznym liściem. Utrzymują gościńce i przyjmują zlecenia na potwory.'),
            'gmContent' => $this->document('Dowódczyni zakonu potajemnie szuka klucza do sieci teleportacyjnej.'),
            'relations' => [
                ['targetEntryId' => $region, 'label' => 'Chroni', 'audience' => 'public'],
                ['targetEntryId' => $ford, 'label' => 'Utrzymuje posterunek', 'audience' => 'public'],
            ],
        ]);

        $eventMonth = $calendar['months'][min(7, count($calendar['months']) - 1)];
        $this->entry('demo-noc-spadajacych-gwiazd', [
            'typeId' => $types['event'],
            'title' => 'Noc Spadających Gwiazd',
            'excerpt' => 'Deszcz błękitnych meteorów, po którym na północy zaczęły budzić się kurhany.',
            'visibility' => 'players',
            'tagIds' => [$this->tags['history']],
            'chronology' => ['precision' => 'day', 'start' => [
                'eraId' => $calendar['eras'][0]['id'], 'year' => 312,
                'monthId' => $eventMonth['id'], 'day' => min(17, (int) $eventMonth['days']),
            ]],
            'publicContent' => $this->document('Przez całą noc niebo przecinały błękitne smugi. Rankiem znaleziono pierwsze puste grobowce i szkło stopione w idealne kręgi.'),
            'gmContent' => $this->document('Meteory były odłamkami pieczęci pod Księżycowym Brodem.'),
            'relations' => [
                ['targetEntryId' => $region, 'label' => 'Miejsce wydarzenia', 'audience' => 'public'],
                ['targetEntryId' => $wardens, 'label' => 'Rozpoczęło działalność', 'audience' => 'public'],
            ],
        ]);

        $system = $this->db->table('rpg_system_universes')->select('system_id')
            ->where('universe_id', $this->universeId)->where('is_active', 1)->orderBy('system_id')->get()->getRowArray();
        $statBlocks = $system ? [[
            'systemId' => (int) $system['system_id'],
            'data' => [
                'details' => ['species' => 'Popielny wilk', 'notes' => 'Niezależna kopia z Compendium'],
                'attributes' => ['movement' => 6, 'wounds' => 12, 'armor' => 1],
                'skills' => ['perception' => 45, 'stealth' => 50],
            ],
            'token' => ['width' => 100, 'height' => 100, 'disposition' => 'hostile', 'hidden' => false],
        ]] : [];
        $this->entry('demo-popielny-wilk', [
            'typeId' => $types['creature'],
            'title' => 'Popielny Wilk',
            'aliases' => ['Wilk z kurhanów'],
            'excerpt' => 'Wielki drapieżnik pozostawiający za sobą zimny popiół zamiast śladów.',
            'visibility' => 'players',
            'tagIds' => [$this->tags['creature'], $this->tags['adventure']],
            'publicContent' => $this->document('Popielny wilk poluje o zmierzchu. Jego skóra przypomina zwęglone drewno, a oczy świecą błękitem.'),
            'gmContent' => $this->document('Wilk traci odporność, gdy usłyszy bicie srebrnego dzwonu z kaplicy Trzech Dróg.'),
            'statBlocks' => $statBlocks,
            'relations' => [['targetEntryId' => $ford, 'label' => 'Teren łowiecki', 'audience' => 'public']],
        ]);

        $this->entry('demo-kamienny-kruk', [
            'typeId' => $types['creature'],
            'title' => 'Kamienny Kruk',
            'aliases' => ['Oko kurhanu'],
            'excerpt' => 'Nieruchoma rzeźba, która ożywa, gdy ktoś naruszy zapieczętowany grobowiec.',
            'visibility' => 'players',
            'tagIds' => [$this->tags['creature']],
            'publicContent' => $this->document('Kamienne kruki obserwują podróżnych z ruin i nagrobków. Przed atakiem słychać trzask pękającego granitu.'),
            'gmContent' => $this->document('Nie widzą osób niosących popiół z legalnie wygaszonego stosu pogrzebowego.'),
            'statBlocks' => $this->statBlocks($system, [
                'details' => ['species' => 'Kamienny kruk', 'role' => 'Zwiadowca'],
                'attributes' => ['movement' => 8, 'wounds' => 7, 'armor' => 3],
                'skills' => ['perception' => 60, 'stealth' => 55],
            ], ['width' => 70, 'height' => 70, 'disposition' => 'hostile', 'hidden' => true]),
            'relations' => [['targetEntryId' => $region, 'label' => 'Występuje w', 'audience' => 'public']],
        ]);

        $this->entry('demo-topielec-z-eren', [
            'typeId' => $types['creature'],
            'title' => 'Topielec z Eren',
            'aliases' => ['Mokry pielgrzym'],
            'excerpt' => 'Napęczniały nieumarły, który naśladuje głosy osób stojących na brzegu.',
            'visibility' => 'players',
            'tagIds' => [$this->tags['creature'], $this->tags['adventure']],
            'publicContent' => $this->document('Topielce wychodzą z Eren podczas mgły. Nigdy nie odpowiadają własnym głosem i unikają światła latarni z olejem jałowcowym.'),
            'gmContent' => $this->document('Każdy nosi monetę poborcy. Oddanie monety rodzinie zmarłego pozwala uspokoić istotę bez walki.'),
            'statBlocks' => $this->statBlocks($system, [
                'details' => ['species' => 'Topielec', 'role' => 'Kontroler'],
                'attributes' => ['movement' => 4, 'wounds' => 15, 'armor' => 0],
                'skills' => ['swim' => 65, 'grapple' => 48],
                'abilities' => ['imitated_voice' => true, 'amphibious' => true],
            ], ['width' => 100, 'height' => 100, 'disposition' => 'hostile', 'hidden' => false]),
            'relations' => [['targetEntryId' => $ford, 'label' => 'Nawiedza', 'audience' => 'public']],
        ]);

        $this->entry('demo-rogaty-zniwiarz', [
            'typeId' => $types['creature'],
            'title' => 'Rogaty Żniwiarz',
            'aliases' => ['Pan Pustych Pól'],
            'excerpt' => 'Wysoka istota z porożem, pojawiająca się na opuszczonych polach po nieudanych żniwach.',
            'visibility' => 'players',
            'tagIds' => [$this->tags['creature'], $this->tags['adventure']],
            'publicContent' => $this->document('Żniwiarz porusza się bezszelestnie między łanami. Zwiastują go odwrócone strachy na wróble i mleko kwaśniejące w zamkniętych naczyniach.'),
            'gmContent' => $this->document('Jest związany z jednym polem. Spalenie ostatniego nieskoszonego snopa odbiera mu możliwość odradzania się.'),
            'statBlocks' => $this->statBlocks($system, [
                'details' => ['species' => 'Duch pól', 'role' => 'Elita'],
                'attributes' => ['movement' => 5, 'wounds' => 28, 'armor' => 2],
                'skills' => ['intimidation' => 58, 'melee' => 55, 'stealth' => 40],
                'abilities' => ['regeneration' => 3, 'fear' => 2],
            ], ['width' => 150, 'height' => 150, 'disposition' => 'hostile', 'hidden' => true]),
            'relations' => [['targetEntryId' => $region, 'label' => 'Nawiedza pola', 'audience' => 'public']],
        ]);

        $this->entry('demo-kult-pustego-slonca', [
            'typeId' => $types['faction'],
            'title' => 'Kult Pustego Słońca',
            'excerpt' => 'Ukryte zagrożenie Marchii.',
            'visibility' => 'gm_only',
            'tagIds' => [$this->tags['secret']],
            'publicContent' => $this->document(''),
            'gmContent' => $this->document('Kult podszywa się pod wędrownych uzdrowicieli i zbiera fragmenty błękitnych meteorów.'),
            'relations' => [
                ['targetEntryId' => $ford, 'label' => 'Działa w pobliżu', 'audience' => 'gm'],
                ['targetEntryId' => $wardens, 'label' => 'Infiltruje', 'audience' => 'gm'],
            ],
        ]);

        echo "Dodano przykładowe Compendium dla świata: {$universe['name']}.\n";
    }

    private function calendar(array $calendar): array
    {
        if (!$calendar['months'] || !$calendar['eras']) {
            $months = [];
            foreach (['Świt', 'Roztopy', 'Siew', 'Deszcze', 'Kwiaty', 'Słońce', 'Żniwa', 'Długi Zmierzch', 'Mgły', 'Liście', 'Szaruga', 'Mróz'] as $name) {
                $months[] = ['name' => $name, 'days' => 30];
            }
            $result = $this->service->updateCalendar($this->universeId, $this->auth, [
                'revision' => $calendar['revision'], 'name' => 'Kalendarz Marchii',
                'months' => $months,
                'eras' => [['name' => 'Era Korony', 'abbreviation' => 'EK', 'epochOrdinal' => 0, 'direction' => 1]],
            ]);
            return $result['calendar'];
        }
        return $calendar;
    }

    private function tags(array $existing): array
    {
        $result = [];
        foreach ($existing as $tag) $result[$this->tagCode($tag['name'])] = (int) $tag['id'];
        foreach ([
            'region' => ['Region', '#64748b'], 'place' => ['Miejsce', '#0ea5e9'],
            'faction' => ['Frakcja', '#8b5cf6'], 'history' => ['Historia', '#d97706'],
            'creature' => ['Bestiariusz', '#dc2626'], 'adventure' => ['Przygoda', '#16a34a'],
            'secret' => ['Sekret MG', '#7f1d1d'],
        ] as $code => [$name, $color]) {
            if (!isset($result[$code])) {
                $created = $this->service->createTag($this->universeId, $this->auth, ['name' => $name, 'color' => $color]);
                $result[$code] = (int) $created['tag']['id'];
            }
        }
        return $result;
    }

    private function entry(string $slug, array $payload): int
    {
        $existing = $this->db->table('compendium_entries e')
            ->select('e.id')->join('compendium_worlds w', 'w.id=e.world_id', 'inner')
            ->where('w.universe_id', $this->universeId)->where('e.slug', $slug)->get()->getRowArray();
        if ($existing) return (int) $existing['id'];
        $created = $this->service->createEntry($this->universeId, $this->auth, ['slug' => $slug] + $payload);
        $entry = $created['entry'];
        $this->service->publish($this->universeId, (int) $entry['id'], $this->auth, ['revision' => (int) $entry['revision']]);
        return (int) $entry['id'];
    }

    private function document(string ...$paragraphs): array
    {
        return ['type' => 'doc', 'content' => array_map(static fn (string $text): array => [
            'type' => 'paragraph', 'content' => $text === '' ? [] : [['type' => 'text', 'text' => $text]],
        ], $paragraphs)];
    }

    private function statBlocks(?array $system, array $data, array $token): array
    {
        return $system ? [[
            'systemId' => (int) $system['system_id'], 'data' => $data, 'token' => $token,
        ]] : [];
    }

    private function tagCode(string $name): string
    {
        $known = ['Region' => 'region', 'Miejsce' => 'place', 'Frakcja' => 'faction',
            'Historia' => 'history', 'Bestiariusz' => 'creature', 'Przygoda' => 'adventure', 'Sekret MG' => 'secret'];
        return $known[$name] ?? strtolower($name);
    }
}
