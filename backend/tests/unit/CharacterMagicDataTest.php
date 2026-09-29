<?php

namespace Tests\Unit;

use App\Services\Magic\CharacterMagicData;
use CodeIgniter\Test\CIUnitTestCase;

final class CharacterMagicDataTest extends CIUnitTestCase
{
    public function testReadsCurrentWfrpTraitsAndSpendsExistingExperiencePath(): void
    {
        $data = [
            'attributes' => ['actual' => ['mag' => 3, 'sw' => 48]],
            'experience' => ['current' => 350, 'total' => 900],
        ];

        $this->assertSame(3, CharacterMagicData::magic($data));
        $this->assertSame(48, CharacterMagicData::willpower($data));
        $this->assertSame(250, CharacterMagicData::spendExperience($data, 100));
        $this->assertSame(250, $data['experience']['current']);
        $this->assertSame(900, $data['experience']['total']);
    }
}
