<?php

use App\Services\Consumption\ConsumptionCatalog;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ConsumptionCatalogTest extends CIUnitTestCase
{
    public function testImportsEveryWorkbookProfileAndSharedEffect(): void
    {
        $catalog = new ConsumptionCatalog();
        $this->assertCount(600, $catalog->profiles());
        $this->assertCount(36, $catalog->effects());
        foreach ($catalog->profiles() as $profile) {
            $this->assertNotNull($catalog->effect($profile['effectId']));
            $this->assertGreaterThanOrEqual(0, $profile['basePricePennies']);
        }
    }

    public function testResolvesTemplateInheritanceInstanceDisableAndOverride(): void
    {
        $catalog = new ConsumptionCatalog();
        $template = ['consumption_profile_id' => 'FOOD-016'];
        $this->assertSame('FOOD-016', $catalog->resolve($template)['profile']['id']);
        $this->assertNull($catalog->resolve($template, ['consumption_mode' => 'disabled'])['profile']);
        $override = $catalog->resolve($template, [
            'consumption_mode' => 'override', 'consumption_profile_id' => 'FOOD-033',
        ]);
        $this->assertSame('instance', $override['source']);
        $this->assertSame('FOOD-033', $override['profile']['id']);
        $this->assertNull($catalog->resolve([])['profile']);
    }

    public function testUnknownHiddenPoisonNeverLeaksItsIdentity(): void
    {
        $catalog = new ConsumptionCatalog();
        $view = $catalog->playerView($catalog->profile('FOOD-596'), 'unknown', 1, ['accessible' => true]);
        $this->assertSame('Kielich czerwonego wina', $view['name']);
        $this->assertSame('', $view['effect']);
        $this->assertSame('', $view['effectWindow']);
        $this->assertSame('', $view['risk']);
        $this->assertSame('Nieznany produkt', $view['kind']);
        $this->assertStringNotContainsString('Sercojad', json_encode($view));
    }

    public function testE35RemainsDisabledAndPricesUsePennies(): void
    {
        $catalog = new ConsumptionCatalog();
        $view = $catalog->playerView($catalog->profile('FOOD-001'), 'identified', 1, ['accessible' => true]);
        $this->assertTrue($view['disabled']);
        $this->assertSame('Produkt wymaga przygotowania.', $view['disabledReason']);
        $this->assertSame(1, $view['basePricePennies']);
    }

    public function testEmptyPortionsAndCombatRestrictionsDisableConsumption(): void
    {
        $catalog = new ConsumptionCatalog();
        $combatProfile = null;
        foreach ($catalog->profiles() as $profile) {
            if (empty($profile['usableInCombat']) && empty($profile['requiresPreparation'])) {
                $combatProfile = $profile;
                break;
            }
        }
        $this->assertNotNull($combatProfile);
        $empty = $catalog->playerView($combatProfile, 'identified', 0, ['accessible' => true]);
        $combat = $catalog->playerView($combatProfile, 'identified', 1, ['accessible' => true, 'inCombat' => true]);
        $this->assertSame('Brak porcji.', $empty['disabledReason']);
        $this->assertSame('Nie można spożyć tego produktu podczas walki.', $combat['disabledReason']);
    }
}
