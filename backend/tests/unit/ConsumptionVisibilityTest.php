<?php

use App\Services\Shop\ShopLegacyMapper;
use App\Services\Shop\ShopBootstrapContextBuilder;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ConsumptionVisibilityTest extends CIUnitTestCase
{
    private function poisonTemplate(): array
    {
        return [
            'id' => 100, 'name' => 'Sercojad w czerwonym winie', 'description' => 'Sercojad',
            'details' => 'Sercojad', 'prize' => 6, 'currency_code' => 'wfrp_empire',
            'consumption_profile_id' => 'FOOD-596', 'img_class' => 'wine', 'charge' => 0,
            'attributes_json' => [], 'mechanics_json' => [], 'mechanics_mode' => 'EXTEND',
        ];
    }

    private function poisonInstance(): array
    {
        return [
            'id' => 200, 'template_id' => 100, 'consumption_mode' => 'override',
            'consumption_profile_id' => 'FOOD-596', 'consumption_identification' => 'unknown',
            'consumption_portions' => 1, 'name_override' => null,
            'note' => 'Sercojad – notatka MG', 'data_override_json' => ['DETAILS' => 'Sercojad'],
        ];
    }

    public function testUnknownPoisonIsNotPresentInAnyInventoryPayloadField(): void
    {
        $mapper = new ShopLegacyMapper();
        $payload = $mapper->inventoryFromInstanceRow(
            ['price_override' => null],
            $this->poisonInstance(),
            $this->poisonTemplate(),
            'ITEM', 'CHAR_1', 'BACKPACK'
        );

        $this->assertSame('Kielich czerwonego wina', $payload['NAME']);
        $this->assertStringNotContainsString('Sercojad', json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->assertSame('', $payload['CONSUMPTION']['effect']);
        $this->assertSame('', $payload['CONSUMPTION']['risk']);
        $this->assertArrayNotHasKey('CONSUMPTION_CONFIGURATION', $payload);
    }

    public function testGmConfigurationContainsTheEditableProfileWithoutChangingPlayerPayload(): void
    {
        $configuration = (new ShopLegacyMapper())->instanceConsumptionConfiguration(
            $this->poisonTemplate(),
            $this->poisonInstance()
        );

        $this->assertSame('override', $configuration['mode']);
        $this->assertSame('instance', $configuration['source']);
        $this->assertSame('FOOD-596', $configuration['resolvedProfileId']);
        $this->assertSame('Sercojad w czerwonym winie', $configuration['resolvedProfile']['name']);
    }

    public function testEditableConsumptionConfigurationIsExcludedFromThePlayerBootstrapPayload(): void
    {
        $builder = new ShopBootstrapContextBuilder();
        $placement = ['id' => 10, 'instance_id' => 200, 'container_id' => 20, 'price_override' => null];
        $instances = [200 => $this->poisonInstance()];
        $templates = [100 => $this->poisonTemplate()];
        $containers = [[
            'id' => 20, 'container_type' => 'CHARACTER', 'system_key' => null,
            'owner_code' => 'CHAR_1', 'name' => 'Plecak', 'shop_id' => null,
        ]];

        $playerRows = $builder->allItemInstances([$placement], $instances, $templates, $containers, [], [], 'CHAR_1');
        $gmRows = $builder->allItemInstances([$placement], $instances, $templates, $containers, [], [], 'CHAR_1', true);

        $this->assertArrayNotHasKey('CONSUMPTION_CONFIGURATION', $playerRows[0]);
        $this->assertSame('FOOD-596', $gmRows[0]['CONSUMPTION_CONFIGURATION']['resolvedProfileId']);
    }
}
