<?php

use CodeIgniter\Test\CIUnitTestCase;
use Config\SubscriptionPlans;

/** @internal */
final class SubscriptionPlansConfigTest extends CIUnitTestCase
{
    public function testExposesExactlyFourValidPublicPlans(): void
    {
        $plans = (new SubscriptionPlans())->plans;

        $this->assertCount(4, $plans);
        $codes = [];
        foreach ($plans as $plan) {
            $this->assertIsString($plan['code']);
            $this->assertNotSame('', $plan['code']);
            $this->assertArrayHasKey('price', $plan);
            $this->assertGreaterThanOrEqual(0, $plan['price']['amountMinor']);
            $this->assertSame('PLN', $plan['price']['currency']);
            $this->assertSame('month', $plan['price']['interval']);
            $this->assertIsArray($plan['limits']);
            $this->assertNotEmpty($plan['features']);
            $this->assertIsBool($plan['highlighted']);
            $codes[] = $plan['code'];
        }

        $this->assertCount(4, array_unique($codes));
        $this->assertSame(1, count(array_filter(
            $plans,
            static function (array $plan): bool {
                return $plan['highlighted'];
            }
        )));
    }
}
