<?php

use App\Services\Token\TokenVisionValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenVisionValidatorTest extends CIUnitTestCase
{
    /** @dataProvider visionModeProvider */
    public function testAcceptsEverySupportedVisionMode(string $mode): void
    {
        $result = TokenVisionValidator::validate([
            'enabled' => true,
            'range' => 600,
            'angle' => 120,
            'direction' => -30,
            'mode' => $mode,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame($mode, $result['data']['mode']);
        $this->assertSame(330.0, $result['data']['direction']);
    }

    public static function visionModeProvider(): array
    {
        return [
            ['basic'],
            ['darkvision'],
            ['light_amplification'],
            ['monochromatic'],
            ['tremorsense'],
        ];
    }

    public function testRejectsUnsupportedVisionMode(): void
    {
        $result = TokenVisionValidator::validate(['mode' => 'xray']);

        $this->assertFalse($result['valid']);
    }
}
