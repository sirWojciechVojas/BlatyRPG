<?php

use App\Services\Shop\ShopItemMechanicsService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ShopItemMechanicsServiceTest extends CIUnitTestCase
{
    public function testNormalizesDeclarativeTestCostsAndEffects(): void
    {
        $service = new ShopItemMechanicsService();
        $mechanics = $service->normalizeMechanics([[
            'code' => 'drink_elixir',
            'labelPl' => 'Wypij eliksir',
            'trigger' => 'consume',
            'handler' => 'dice_test',
            'check' => [
                'enabled' => true,
                'formula' => '1d100',
                'targetKey' => 'T',
                'difficulty' => 150,
            ],
            'cost' => ['quantity' => 1],
            'effects' => [[
                'when' => 'success',
                'type' => 'heal',
                'target' => 'self',
                'value' => '1d10',
            ]],
        ]]);

        $this->assertCount(1, $mechanics);
        $this->assertSame('DRINK_ELIXIR', $mechanics[0]['code']);
        $this->assertSame('CONSUME', $mechanics[0]['trigger']);
        $this->assertSame('DICE_TEST', $mechanics[0]['handler']);
        $this->assertSame(100, $mechanics[0]['check']['difficulty']);
        $this->assertSame(1, $mechanics[0]['cost']['quantity']);
        $this->assertSame('HEAL', $mechanics[0]['effects'][0]['type']);
    }

    public function testResolvesClassGenreAndTemplateOverrides(): void
    {
        $service = new ShopItemMechanicsService();
        $class = [[
            'code' => 'USE',
            'labelPl' => 'Użyj klasy',
        ]];
        $genre = [[
            'code' => 'USE',
            'labelPl' => 'Użyj rodzaju',
        ], [
            'code' => 'PASSIVE',
            'labelPl' => 'Pasywna',
        ]];
        $template = [[
            'code' => 'USE',
            'labelPl' => 'Użyj szablonu',
        ]];

        $extended = $service->resolve($class, $genre, $template, 'EXTEND');
        $this->assertCount(2, $extended);
        $this->assertSame('Użyj szablonu', $extended[0]['labelPl']);
        $this->assertSame('TEMPLATE', $extended[0]['source']);
        $this->assertSame('GENRE', $extended[1]['source']);

        $inherited = $service->resolve($class, $genre, $template, 'INHERIT');
        $this->assertSame('Użyj rodzaju', $inherited[0]['labelPl']);
        $this->assertSame('GENRE', $inherited[0]['source']);

        $replaced = $service->resolve($class, $genre, $template, 'REPLACE');
        $this->assertCount(1, $replaced);
        $this->assertSame('TEMPLATE', $replaced[0]['source']);
    }
}
