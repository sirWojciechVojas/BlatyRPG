<?php

use App\Services\Token\TokenStatusValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenStatusValidatorTest extends CIUnitTestCase
{
    public function testNormalizesUniqueQuickStatusCodes(): void
    {
        $result = TokenStatusValidator::validate([
            'Poisoned', 'bleeding', 'poisoned',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(['poisoned', 'bleeding'], $result['data']);
    }

    public function testRejectsObjectsAndUnsafeCodes(): void
    {
        $this->assertFalse(TokenStatusValidator::validate([
            ['code' => 'poisoned'],
        ])['valid']);
        $this->assertFalse(TokenStatusValidator::validate(['bad status'])['valid']);
    }
}
