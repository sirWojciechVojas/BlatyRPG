<?php

use App\Services\Consumption\ConsumptionCatalog;
use App\Services\Consumption\ConsumptionService;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ConsumptionRulesTest extends CIUnitTestCase
{
    public function testOneTestBonusIsConsumedAndBonusesDoNotStack(): void
    {
        $service = new ConsumptionService(null, new ConsumptionCatalog(), static fn (): int => 40);
        $data = $this->characterData();
        $data['consumption']['effects']['warming'] = [
            'key' => 'warming', 'value' => 10, 'expiresAtMinute' => 120, 'kind' => 'next_test:odporność',
        ];

        $result = $service->testCharacter($data, 'odp', 0, 'odporność');

        $this->assertSame(50, $result['target']);
        $this->assertTrue($result['success']);
        $this->assertArrayNotHasKey('warming', $data['consumption']['effects']);
    }

    public function testToughnessAndStrongHeadTestsIncludeTalentAndCurrentPenalty(): void
    {
        $service = new ConsumptionService(null, new ConsumptionCatalog(), static fn (): int => 26);
        $data = $this->characterData();
        $data['skills'] = ['Mocna Głowa'];
        $data['consumption']['effects']['alcohol'] = [
            'key' => 'alcohol', 'value' => -5, 'expiresAtMinute' => 120, 'kind' => 'alcohol',
        ];

        $result = $service->testCharacter($data, 'odp', -20, 'mocna głowa');

        $this->assertSame(25, $result['target']);
        $this->assertFalse($result['success']);
    }

    public function testHealingIsLimitedByMaximumWounds(): void
    {
        $service = new ConsumptionService(null, new ConsumptionCatalog(), static fn (): int => 1);
        $data = $this->characterData(9);
        $method = new ReflectionMethod($service, 'applyKnownEffect');
        $method->setAccessible(true);
        $events = [];
        $profile = null;
        foreach ((new ConsumptionCatalog())->profiles() as $candidate) {
            if (($candidate['effectId'] ?? '') === 'E33') {
                $profile = $candidate;
                break;
            }
        }
        $this->assertNotNull($profile);
        $method->invokeArgs($service, [&$data, $profile, [], 0, &$events]);

        $this->assertLessThanOrEqual(10, $data['attributes']['actual']['zyw']);
    }

    private function characterData(int $wounds = 10): array
    {
        return [
            'attributes' => [
                'actual' => ['odp' => 40, 'wt' => 4, 'zyw' => $wounds, 'po' => 0],
                'start' => ['zyw' => 10], 'advances' => ['zyw' => 0],
            ],
            'consumption' => ['effects' => []],
        ];
    }
}
