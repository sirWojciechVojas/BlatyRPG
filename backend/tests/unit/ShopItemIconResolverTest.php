<?php

use App\Services\Shop\ShopItemIconResolver;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ShopItemIconResolverTest extends CIUnitTestCase
{
    public function testShieldNeverUsesMailArmorFallback(): void
    {
        $resolver = new ShopItemIconResolver();

        $icon = $resolver->resolve([
            'NAME' => 'Tarcza okuta',
            'ITEM_CLASS' => 'ARMOR',
            'ITEM_GENRE' => 'SHIELD',
            'IMG_CLASS' => 'v0619',
        ]);

        $this->assertSame('v1240', $icon);
    }

    public function testDifferentShieldFormsUseDifferentSprites(): void
    {
        $resolver = new ShopItemIconResolver();

        $this->assertSame('v1233', $resolver->resolve([
            'NAME' => 'Pawez drewniana',
            'ITEM_CLASS' => 'ARMOR',
            'IMG_CLASS' => 'v0619',
        ]));
        $this->assertSame('v1252', $resolver->resolve([
            'NAME' => 'Puklerz stalowy',
            'ITEM_CLASS' => 'ARMOR',
            'IMG_CLASS' => 'v0619',
        ]));
    }

    public function testLegacyGenericIconsAreCorrectedByMeaning(): void
    {
        $resolver = new ShopItemIconResolver();
        $cases = [
            ['Chleb razowy', 'FOOD', 'v1148', 'v0112'],
            ['Pergamin kupiecki', 'STATIONERY', 'v0724', 'v1195'],
            ['Helm zelazny', 'ARMOR', 'v0619', 'v0496'],
            ['Miecz cechowy', 'WEAPON', 'v0170', 'v1289'],
            ['Pierscien srebrny', 'JEWELLERY', 'v1041', 'v1151'],
        ];

        foreach ($cases as [$name, $class, $current, $expected]) {
            $this->assertSame($expected, $resolver->resolve([
                'NAME' => $name,
                'ITEM_CLASS' => $class,
                'IMG_CLASS' => $current,
            ]), $name);
        }
    }

    public function testManualNonGenericSpriteIsPreserved(): void
    {
        $resolver = new ShopItemIconResolver();

        $this->assertSame('v1250', $resolver->resolve([
            'NAME' => 'Tarcza herbowa',
            'ITEM_CLASS' => 'ARMOR',
            'IMG_CLASS' => 'v1250',
        ]));
    }

    public function testExplicitPickerChoicePreservesFormerGenericSprite(): void
    {
        $resolver = new ShopItemIconResolver();
        $item = [
            'NAME' => 'Eliksir leczenia',
            'ITEM_CLASS' => 'ALCHEMY',
            'ITEM_GENRE' => 'POTION',
            'IMG_CLASS' => 'v1089',
        ];

        $this->assertSame('v1074', $resolver->resolve($item));
        $this->assertSame('v1089', $resolver->resolve($item, 'v1089', true));
    }
}
