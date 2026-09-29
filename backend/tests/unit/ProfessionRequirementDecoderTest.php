<?php

use App\Services\Profession\ProfessionRequirementDecoder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/** @internal */
final class ProfessionRequirementDecoderTest extends CIUnitTestCase
{
    private BaseConnection $decoderDb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->decoderDb = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
    }

    protected function tearDown(): void
    {
        $this->decoderDb->close();
        parent::tearDown();
    }

    public function testDecodesSkillIdsSpecializationsAndAlternatives(): void
    {
        $decoder = new ProfessionRequirementDecoder($this->decoderDb);
        $result = $decoder->decode(
            '3,12,14(2)|14(6),14(15),22,29,46(13),46(9)',
            'skills'
        );

        $this->assertTrue($result['decoded']);
        $this->assertSame(
            'Czytanie i pisanie, Leczenie, Nauka (astronomia) lub '
            . 'Nauka (historia), Nauka (teologia), Przekonywanie, '
            . 'Spostrzegawczość, Znajomość języka (klasyczny), '
            . 'Znajomość języka (staroświatowy — Reikspiel)',
            $result['display']
        );
    }

    public function testPreservesFreeTextTalentLists(): void
    {
        $decoder = new ProfessionRequirementDecoder($this->decoderDb);
        $result = $decoder->decode(
            'błyskawiczny blok, broń specjalna (kusze)',
            'talents'
        );

        $this->assertFalse($result['decoded']);
        $this->assertSame($result['raw'], $result['display']);
    }

    public function testDecodesNestedSpecializationChoices(): void
    {
        $decoder = new ProfessionRequirementDecoder($this->decoderDb);
        $result = $decoder->decode('46(2, 6|8)', 'skills');

        $this->assertSame(
            'Znajomość języka (bretoński, kislevski lub tileański)',
            $result['display']
        );
    }

    public function testBuildsSelectableOptionsForBaseAndSpecializedSkills(): void
    {
        $decoder = new ProfessionRequirementDecoder($this->decoderDb);
        $options = $decoder->options('skills');

        $byRaw = array_column($options, null, 'raw');
        $this->assertSame('Nauka (różne)', $byRaw['14']['display']);
        $this->assertSame('Nauka (astronomia)', $byRaw['14(2)']['display']);
        $this->assertSame(
            'Znajomość języka (kislevski)',
            $byRaw['46(6)']['display']
        );
    }
}
