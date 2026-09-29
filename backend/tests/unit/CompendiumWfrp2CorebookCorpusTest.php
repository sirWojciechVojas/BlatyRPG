<?php

use App\Services\Compendium\CompendiumWfrp2CorebookImporter;
use PHPUnit\Framework\TestCase;

final class CompendiumWfrp2CorebookCorpusTest extends TestCase
{
    private function corpusPath(): string
    {
        return APPPATH . 'Data/Compendium/wfrp2-corebook-pl.json';
    }

    private function corpus(): array
    {
        $decoded = json_decode((string) file_get_contents($this->corpusPath()), true);
        $this->assertIsArray($decoded);
        return $decoded;
    }

    public function testCorpusContainsCuratedEntriesInsteadOfCompleteChapters(): void
    {
        $reflection = new ReflectionClass(CompendiumWfrp2CorebookImporter::class);
        /** @var CompendiumWfrp2CorebookImporter $importer */
        $importer = $reflection->newInstanceWithoutConstructor();
        $validation = $importer->validate($this->corpusPath());

        $this->assertTrue($validation['valid'], implode("\n", $validation['errors']));
        $this->assertSame(83, $validation['records']);
        $this->assertSame(34, $validation['bestiaryEntries']);

        $corpus = $this->corpus();
        foreach ($corpus['records'] as $record) {
            $this->assertArrayNotHasKey('pages', $record);
            $this->assertNotSame('chapter', $record['kind']);
            $this->assertNotEmpty($record['content']);
            foreach ($record['source']['pdfPages'] as $page) {
                $this->assertGreaterThanOrEqual(1, $page);
                $this->assertLessThanOrEqual(269, $page);
            }
        }
        $this->assertSame(12, $corpus['counts']['rulesEntries']);
        $this->assertSame(27, $corpus['counts']['worldEntries']);
        $this->assertSame(14, $corpus['counts']['scenarioEntries']);
        $this->assertSame(19, $corpus['counts']['creaturesAndAnimals']);
        $this->assertSame(11, $corpus['counts']['genericNpcProfiles']);
        $this->assertSame(4, $corpus['counts']['namedNpcProfiles']);
    }

    public function testHistoryEntriesProvideAnImperialTimeline(): void
    {
        $history = array_values(array_filter(
            $this->corpus()['records'],
            static fn (array $record): bool => ($record['entryType'] ?? null) === 'history'
        ));

        $this->assertCount(11, $history);
        foreach ($history as $record) {
            $this->assertContains($record['chronology']['precision'], ['year', 'range']);
            $this->assertGreaterThan(0, $record['chronology']['start']['year']);
            if ($record['chronology']['precision'] === 'range') {
                $this->assertGreaterThanOrEqual(
                    $record['chronology']['start']['year'],
                    $record['chronology']['end']['year']
                );
            }
        }
        $years = array_map(
            static fn (array $record): int => (int) $record['chronology']['start']['year'],
            $history
        );
        sort($years);
        $this->assertSame(
            [1, 1111, 1124, 1152, 1547, 2010, 2051, 2132, 2320, 2387, 2522],
            $years
        );
    }

    public function testBestiaryProfilesUseCreatureEntriesAndCompleteWfrp2StatLines(): void
    {
        $expectedKeys = [
            'ww', 'us', 'k', 'odp', 'zr', 'int', 'sw', 'ogd',
            'a', 'zyw', 's', 'wt', 'sz', 'mag', 'po', 'pp',
        ];
        $bestiary = array_values(array_filter(
            $this->corpus()['records'],
            static fn (array $record): bool => !empty($record['bestiary'])
        ));

        $this->assertCount(34, $bestiary);
        foreach ($bestiary as $record) {
            $this->assertSame($expectedKeys, array_keys($record['profile']['attributes']));
            $this->assertSame('creature', $record['entryType']);
            $this->assertGreaterThan(0, $record['profile']['token']['width']);
            $this->assertGreaterThan(0, $record['profile']['token']['height']);
        }
        $this->assertContains('Goblin', array_column($bestiary, 'title'));
        $this->assertContains('Skaven', array_column($bestiary, 'title'));
        $this->assertContains('Niedźwiedź', array_column($bestiary, 'title'));
        $this->assertContains('Gerhard Schiller', array_column($bestiary, 'title'));
        $this->assertContains('Ojciec Dietrich', array_column($bestiary, 'title'));
        $this->assertContains('Strażnik miejski', array_column($bestiary, 'title'));
        $this->assertContains('Kieszonkowiec', array_column($bestiary, 'title'));
    }

    public function testWorldLoreScenarioAndNamedCharactersAreSeparated(): void
    {
        $records = $this->corpus()['records'];
        $byTitle = [];
        foreach ($records as $record) {
            $byTitle[$record['title']] = $record;
        }

        $this->assertSame('world', $byTitle['Imperium']['scope']);
        $this->assertSame('place', $byTitle['Imperium']['entryType']);
        $this->assertSame('world', $byTitle['Burza Chaosu']['scope']);
        $this->assertSame('history', $byTitle['Burza Chaosu']['entryType']);
        $this->assertSame('scenario', $byTitle['Przez ostępy Drakwaldu']['scope']);
        $this->assertSame('scenario', $byTitle['Rytuał zemsty Babuni Moescher']['scope']);

        foreach (['Gerhard Schiller', 'Babunia Moescher', 'Hans Baumer'] as $title) {
            $record = $byTitle[$title];
            $this->assertSame('named_npc', $record['kind']);
            $this->assertSame('named', $record['npcKind']);
            $this->assertSame('person', $record['type']);
            $this->assertNotEmpty($record['identity']['givenName']);
            $this->assertNotEmpty($record['identity']['familyName']);
        }
        $dietrich = $byTitle['Ojciec Dietrich'];
        $this->assertSame('Dietrich', $dietrich['identity']['givenName']);
        $this->assertNull($dietrich['identity']['familyName']);
        $this->assertSame('Ojciec', $dietrich['identity']['honorific']);
        $this->assertSame('named', $dietrich['npcKind']);
        $this->assertSame(37, $dietrich['profile']['attributes']['ww']);
        $this->assertSame(13, $dietrich['profile']['attributes']['zyw']);

        $genericTitles = [
            'Kieszonkowiec', 'Kowal', 'Kramarz', 'Najmita',
            'Strażnik miejski', 'Szuler', 'Pirat', 'Zawadiaka',
            'Zbir', 'Zbój', 'Żebrak',
        ];
        foreach ($genericTitles as $title) {
            $record = $byTitle[$title];
            $this->assertSame('generic_npc', $record['kind']);
            $this->assertSame('generic', $record['npcKind']);
            $this->assertSame('person', $record['type']);
            $this->assertTrue($record['identity']['archetype']);
            $this->assertNull($record['identity']['givenName']);
            $this->assertNull($record['identity']['familyName']);
            $this->assertFalse($record['identity']['sourceSuppliedName']);
            $this->assertNotEmpty($record['profile']['career']);
        }
        $this->assertSame('złodziej', $byTitle['Kieszonkowiec']['profile']['career']);
        $this->assertSame('strażnik', $byTitle['Strażnik miejski']['profile']['career']);
    }

    public function testOfficialHorseErrataIsAppliedToStructuredProfiles(): void
    {
        $byTitle = [];
        foreach ($this->corpus()['records'] as $record) {
            $byTitle[$record['title']] = $record;
        }

        $ridingHorse = $byTitle['Koń wierzchowy'];
        $destrier = $byTitle['Rumak'];
        $this->assertSame(38, $ridingHorse['profile']['attributes']['k']);
        $this->assertSame(12, $ridingHorse['profile']['attributes']['zyw']);
        $this->assertSame(45, $destrier['profile']['attributes']['k']);
        $this->assertSame(18, $destrier['profile']['attributes']['zyw']);
        $this->assertContains(269, $ridingHorse['source']['pdfPages']);
        $this->assertContains(269, $destrier['source']['pdfPages']);
        $this->assertNotEmpty($ridingHorse['source']['errataApplied']);
        $this->assertNotEmpty($destrier['source']['errataApplied']);
    }

    public function testImporterProjectsProfilesIntoTheExistingBestiaryContract(): void
    {
        $source = (string) file_get_contents(
            APPPATH . 'Services/Compendium/CompendiumWfrp2CorebookImporter.php'
        );

        $this->assertStringContainsString("'type_id' => \$this->typeIds[\$entryType]", $source);
        $this->assertStringContainsString("'stat_blocks_json' => \$this->json(\$statBlocks)", $source);
        $this->assertStringContainsString("'status' => 'verified', 'usable' => 1", $source);
        $this->assertStringContainsString("'visibility' => 'gm_only'", $source);
        $this->assertStringContainsString("'source_uri' => \$sourceUri", $source);
        $this->assertStringContainsString('removeObsoleteRecords', $source);
        $this->assertStringContainsString("'chronology_json' => \$chronology['json']", $source);
    }
}
