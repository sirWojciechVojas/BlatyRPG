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

    public function testUsesHealthAndMovementAsDefaultBars(): void
    {
        $resources = TokenResourceValidator::stored([]);

        $this->assertSame(
            ['HP', 'PR'],
            array_column(array_slice($resources['bars'], 0, 2), 'label')
        );
        $this->assertSame(['#d95d55', '#4caf72'], array_column(
            array_slice($resources['bars'], 0, 2),
            'color'
        ));
        $this->assertTrue($resources['bars'][0]['enabled']);
        $this->assertTrue($resources['bars'][1]['enabled']);
    }

    public function testUpgradesPreviousEmptyBarDefaults(): void
    {
        $bars = array_map(static fn (string $color): array => [
            'enabled' => false, 'label' => '', 'value' => 0, 'max' => 0,
            'color' => $color, 'attributePath' => '', 'maxAttributePath' => '',
        ], ['#4caf72', '#d95d55', '#4f91d9', '#d5a64f']);

        $resources = TokenResourceValidator::stored(['bars' => $bars]);

        $this->assertSame('HP', $resources['bars'][0]['label']);
        $this->assertSame('PR', $resources['bars'][1]['label']);
    }
}
