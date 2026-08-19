<?php

use App\Services\Shop\ShopLegacyMapper;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ShopLegacyMapperTest extends CIUnitTestCase
{
    public function testTemplateToLegacyKeepsContractShape(): void
    {
        $mapper = new ShopLegacyMapper();

        $result = $mapper->templateToLegacy([
            'id' => 15,
            'name' => 'Sztylet',
            'description' => 'Krotka bron.',
            'details' => 'Detal',
            'item_class' => 'weapon',
            'item_id' => '13',
            'item_genre' => 'melee',
            'img_class' => 'v0100',
            'prize' => 120,
            'currency_code' => 'wfrp_bretonnia',
            'charge' => 5,
            'draft' => 1,
            'weapon_json' => ['damage' => 3],
            'attributes_json' => ['FAST', 'PRECISE'],
            'mechanics_json' => [['code' => 'ATTACK']],
            'mechanics_mode' => 'replace',
        ]);

        $this->assertSame(15, $result['ID']);
        $this->assertSame('Sztylet', $result['NAME']);
        $this->assertSame('WEAPON', $result['ITEM_CLASS']);
        $this->assertSame('MELEE', $result['ITEM_GENRE']);
        $this->assertSame(120, $result['PRIZE']);
        $this->assertSame('wfrp_bretonnia', $result['CURRENCY']);
        $this->assertSame(5, $result['CHARGE']);
        $this->assertSame(1, $result['DRAFT']);
        $this->assertSame(['damage' => 3], $result['WEAPON']);
        $this->assertSame(['FAST', 'PRECISE'], $result['ATTRIBUTES']);
        $this->assertSame([['code' => 'ATTACK']], $result['MECHANICS']);
        $this->assertSame('REPLACE', $result['MECHANICS_MODE']);
    }

    public function testTemplateReadKeepsExplicitFormerGenericIcon(): void
    {
        $mapper = new ShopLegacyMapper();

        $result = $mapper->templateToLegacy([
            'id' => 5,
            'name' => 'Eliksir leczenia',
            'item_class' => 'ALCHEMY',
            'item_genre' => 'POTION',
            'img_class' => 'v1089',
        ]);

        $this->assertSame('v1089', $result['IMG_CLASS']);
        $this->assertSame('v1089', $result['ICON']);
        $this->assertSame('v1089', $result['sprite']);
    }

    public function testInventoryFromTemplateRowIncludesLegacyFields(): void
    {
        $mapper = new ShopLegacyMapper();

        $result = $mapper->inventoryFromTemplateRow(
            ['id' => 4, 'template_id' => 9, 'quantity' => 2, 'price_override' => 150],
            ['name' => 'Mlot', 'description' => 'Ciezki', 'img_class' => 'v0200', 'prize' => 200, 'charge' => 0],
            'PLAYER',
            'BG1',
            'PLECY'
        );

        $this->assertSame(4, $result['ID']);
        $this->assertSame(9, $result['INV_ID']);
        $this->assertSame(2, $result['QUANTITY']);
        $this->assertSame('PLAYER', $result['OWNER_OPT']);
        $this->assertSame('BG1', $result['OWNER']);
        $this->assertSame(150, $result['PERSONAL_COST']);
    }

    public function testInventoryFromInstanceRowKeepsOverrides(): void
    {
        $mapper = new ShopLegacyMapper();

        $result = $mapper->inventoryFromInstanceRow(
            ['price_override' => 90],
            [
                'id' => 11,
                'template_id' => 5,
                'name_override' => 'Miecz rodowy',
                'note' => 'Pamiatka',
                'data_override_json' => [
                    'CHARGE' => 42,
                    'IMG_CLASS' => 'v0999',
                    'ATTRIBUTES' => ['HEAVY', 'DURABLE'],
                ],
            ],
            ['name' => 'Miecz', 'description' => 'Opis', 'img_class' => 'v0010', 'prize' => 120, 'charge' => 10],
            'PLAYER',
            'BG2',
            'PLECY'
        );

        $this->assertSame(11, $result['ID']);
        $this->assertSame('Miecz rodowy', $result['NAME']);
        $this->assertSame('Pamiatka', $result['DESCRIPTION']);
        $this->assertSame(90, $result['PERSONAL_COST']);
        $this->assertSame(42, $result['CHARGE']);
        $this->assertSame('v0999', $result['IMG_CLASS']);
        $this->assertSame('v0999', $result['ICON']);
        $this->assertSame('v0999', $result['icon']);
        $this->assertSame(['HEAVY', 'DURABLE'], $result['ATTRIBUTES']);
        $this->assertSame('PLAYER', $result['OWNER_OPT']);
        $this->assertSame('BG2', $result['OWNER']);
    }

    public function testLegacyShopRowUsesConcreteItemNameInsteadOfTechnicalOwnerLabel(): void
    {
        $mapper = new ShopLegacyMapper();

        $result = $mapper->shopEntryFromTemplateRow(
            [
                'template_id' => 7,
                'quantity' => 3,
                'price_override' => null,
            ],
            [
                'name' => 'Lina konopna',
                'description' => 'Mocna lina.',
                'img_class' => 'v0201',
                'prize' => 90,
                'charge' => 35,
            ]
        );

        $this->assertSame('Lina konopna', $result['NAME']);
        $this->assertSame('Lina konopna', $result['PERSONAL_PSEU']);
        $this->assertSame(3, $result['QUANTITY']);
    }

    public function testProfileAndCatalogMappingsHaveExpectedKeys(): void
    {
        $mapper = new ShopLegacyMapper();

        $profile = $mapper->profileToApi([
            'shop_id' => 3,
            'type_id' => 'armorer',
            'signboard_name' => 'Kuznia',
            'owner_code' => 'BG1',
            'owner_name' => 'Hugo',
            'signboard_alt_names_json' => ['Kuznia Hugo'],
            'category_tags_json' => ['metal'],
            'world_profile_id' => 'standard',
            'location_type' => 'miasto',
            'legal_status' => 'legal',
            'wealth_tier' => 'bogaty',
            'reputation' => 'dobra',
            'seasonality' => 'caloroczny',
            'counterfeit_risk' => 12,
            'pricing_config_json' => [
                'minimumPrice' => 1,
                'roundingStep' => 1,
                'enabledModifiers' => ['shopType' => true],
            ],
        ]);

        $catalog = $mapper->catalogNodeToApi([
            'node_key' => 'armorer',
            'parent_key' => 'metal_arms',
            'level' => 'type',
            'name_pl' => 'Platnerz',
            'name_en' => 'Armorer',
            'description_pl' => 'Zbroje',
            'payload_json' => [
                'typicalLocations' => ['miasto', 'forteca'],
                'legalStatus' => 'licensed',
                'traits' => ['craft'],
                'articleSeeds' => ['zbroja plytowa'],
                'suggestionRules' => ['requiredItemClasses' => ['ARMOR']],
            ],
        ]);

        $world = $mapper->worldProfileToApi([
            'id' => 'standard',
            'label_pl' => 'Standard',
            'label_en' => 'Standard',
            'description' => 'Domyslny',
            'impact_summary_pl' => 'Balans',
            'modifiers_json' => ['legalityBias' => ['legal' => 6]],
        ]);

        $this->assertSame(3, $profile['shopId']);
        $this->assertSame('armorer', $profile['typeId']);
        $this->assertSame('Kuznia', $profile['signboardName']);
        $this->assertSame(['metal'], $profile['categoryTags']);
        $this->assertSame(1, $profile['pricingConfig']['minimumPrice']);
        $this->assertTrue($profile['pricingConfig']['enabledModifiers']['shopType']);

        $this->assertSame('armorer', $catalog['id']);
        $this->assertSame('metal_arms', $catalog['parentId']);
        $this->assertSame(['miasto', 'forteca'], $catalog['typicalLocations']);
        $this->assertSame('licensed', $catalog['legalStatus']);
        $this->assertSame(['craft'], $catalog['traits']);
        $this->assertSame(['zbroja plytowa'], $catalog['articleSeeds']);
        $this->assertSame(['requiredItemClasses' => ['ARMOR']], $catalog['suggestionRules']);

        $this->assertSame('standard', $world['id']);
        $this->assertSame('Standard', $world['labelPl']);
        $this->assertSame(['legalityBias' => ['legal' => 6]], $world['modifiers']);
    }
}
