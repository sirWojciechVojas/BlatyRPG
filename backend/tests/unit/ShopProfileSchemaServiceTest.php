<?php

use App\Services\Shop\ShopProfileSchemaService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ShopProfileSchemaServiceTest extends CIUnitTestCase
{
    public function testOldProfileGetsBackwardCompatibleMarketDefaults(): void
    {
        $settings = ShopProfileSchemaService::normalizeSettings(null);

        $this->assertSame('normal', $settings['demandLevel']);
        $this->assertSame(0.0, $settings['availabilityBias']);
        $this->assertNull($settings['buybackBudget']);
        $this->assertSame([], $settings['reputationByActor']);
    }

    public function testValidationRejectsExtremeAndContradictoryValues(): void
    {
        $errors = ShopProfileSchemaService::validate([
            'counterfeitRisk' => 120,
            'marketSettings' => ['availabilityBias' => -90],
            'marketEvents' => [[
                'name' => '', 'startsAt' => '2026-08-10', 'endsAt' => '2026-08-01',
                'multiplier' => 9, 'availabilityDelta' => -150,
            ]],
            'pricingConfig' => [
                'guardrails' => ['buyMinMultiplier' => 2, 'buyMaxMultiplier' => 1],
            ],
        ]);

        $this->assertArrayHasKey('counterfeitRisk', $errors);
        $this->assertArrayHasKey('marketSettings.availabilityBias', $errors);
        $this->assertArrayHasKey('marketEvents.0.name', $errors);
        $this->assertArrayHasKey('marketEvents.0.endsAt', $errors);
        $this->assertArrayHasKey('pricingConfig.guardrails.buyMaxMultiplier', $errors);
    }

    public function testPortableExportRemovesCampaignSpecificRelations(): void
    {
        $document = ShopProfileSchemaService::portableProfile([
            'typeId' => 'armorer', 'shopId' => 99,
            'marketSettings' => ['reputationByActor' => ['BG1' => 'znakomita']],
            'marketEvents' => [[
                'name' => 'Wojna', 'templateIds' => [10, 20], 'multiplier' => 1.2,
            ]],
        ]);

        $this->assertSame('blatyrpg.shop-profile', $document['schema']);
        $this->assertArrayNotHasKey('shopId', $document['profile']);
        $this->assertSame([], $document['marketSettings']['reputationByActor']);
        $this->assertSame([], $document['marketEvents'][0]['templateIds']);
    }
}
