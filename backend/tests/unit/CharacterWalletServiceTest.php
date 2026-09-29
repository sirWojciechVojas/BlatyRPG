<?php

use App\Services\Character\CharacterException;
use App\Services\Character\CharacterWalletService;
use CodeIgniter\Test\CIUnitTestCase;

final class CharacterWalletServiceTest extends CIUnitTestCase
{
    public function testItValidatesMultipleKnownCurrencies(): void
    {
        $result = CharacterWalletService::validateWalletsPayload([
            'wallets' => [
                ['currencyCode' => 'wfrp_empire', 'balance' => 252],
                ['currencyCode' => 'wfrp_bretonnia', 'balance' => 11],
            ],
        ], ['wfrp_empire', 'wfrp_bretonnia']);

        $this->assertSame([
            'wfrp_empire' => 252,
            'wfrp_bretonnia' => 11,
        ], $result);
    }

    public function testItRejectsUnknownCurrencies(): void
    {
        $this->expectException(CharacterException::class);
        CharacterWalletService::validateWalletsPayload([
            'wallets' => [['currencyCode' => 'warpstone', 'balance' => 2]],
        ], ['wfrp_empire']);
    }

    public function testItRequiresAtLeastOneWallet(): void
    {
        $this->expectException(CharacterException::class);
        CharacterWalletService::validateWalletsPayload([
            'wallets' => [],
        ], ['wfrp_empire']);
    }
}
