<?php

use App\Services\Token\TokenResourceValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenResourceValidatorTest extends CIUnitTestCase
{
    public function testNormalizesFourBarsAndThreeBubbles(): void
    {
        $result = TokenResourceValidator::validate([
            'bars' => [[
                'enabled' => true, 'label' => 'HP', 'value' => 8, 'max' => 12,
                'color' => '#D95D55', 'attributePath' => 'attributes.actual.hp',
                'maxAttributePath' => 'attributes.actual.hp_max',
            ]],
            'bubbles' => [[
                'enabled' => true, 'label' => 'KP', 'value' => 2,
                'position' => 'bottom-right', 'attributePath' => 'details.kp',
            ]],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertCount(4, $result['data']['bars']);
        $this->assertCount(3, $result['data']['bubbles']);
        $this->assertSame('#d95d55', $result['data']['bars'][0]['color']);
    }

    public function testRejectsUnsafeAttributePath(): void
    {
        $result = TokenResourceValidator::validate(['bubbles' => [[
            'enabled' => true, 'attributePath' => '__proto__.hp',
        ]]]);

        $this->assertFalse($result['valid']);
    }
}
