<?php

namespace Tests\Unit;

use App\Services\Profession\ProfessionPathResolver;
use CodeIgniter\Test\CIUnitTestCase;

final class ProfessionPathResolverTest extends CIUnitTestCase
{
    public function testResolvesQualifiersTyposAndLiteralNewlines(): void
    {
        $resolver = new ProfessionPathResolver();
        $index = $resolver->nameIndex([
            6 => 'cyrkowiec',
            84 => 'mistrz gildii',
            85 => 'mistrz rzemiosła',
            105 => 'wędrowny czarodziej',
        ]);

        $items = $resolver->resolveList(
            'Mistrz gilidii, Wędrowny Czarodziej (tylko kolegium złota),'
                . '\\nMistrz Rzemiosła (grawer), dowolna profesja Cyrkowca',
            $index
        );

        $this->assertSame([84, 105, 85, 6], array_column(
            $items,
            'professionId'
        ));
        $this->assertNotContains(false, array_column($items, 'linked'));
    }

    public function testLegacyPathsKeepUnknownNamesReadable(): void
    {
        $resolver = new ProfessionPathResolver();
        $paths = $resolver->legacyPaths('wfrp2ed', [
            ['id' => 154, 'name' => 'Mistrz Inżynierii'],
            ['id' => 116, 'name' => 'Artylerzysta'],
            ['id' => 72, 'name' => 'Inżynier'],
            ['id' => 85, 'name' => 'Mistrz Rzemiosła'],
            ['id' => 84, 'name' => 'Mistrz Gildii'],
            ['id' => 23, 'name' => 'Najemnik'],
            ['id' => 102, 'name' => 'Uczony'],
            ['id' => 96, 'name' => 'Sierżant'],
        ]);

        $this->assertSame(116, $paths[154]['entries'][0]['professionId']);
        $this->assertNull($paths[154]['entries'][1]['professionId']);
        $this->assertSame(
            'Chaos Engineer (Tome of Corruption)',
            $paths[154]['entries'][1]['name']
        );
        $this->assertContains(102, array_column(
            $paths[154]['exits'],
            'professionId'
        ));
        $this->assertContains(false, array_column(
            $paths[154]['entries'],
            'linked'
        ));
    }
}
