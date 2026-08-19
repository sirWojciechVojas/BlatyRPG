<?php

use App\Services\Shop\ShopCurrencyService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ShopCurrencyServiceTest extends CIUnitTestCase
{
    public function testWarhammerCurrenciesContainImperialAndBretonnianUnits(): void
    {
        $currencies = (new ShopCurrencyService())->definitionsForSystem('wfrp2ed');

        $empire = $this->currencyByCode($currencies, 'wfrp_empire');
        $bretonnia = $this->currencyByCode($currencies, 'wfrp_bretonnia');

        $this->assertSame(
            ['gold_crown', 'silver_shilling', 'brass_penny'],
            array_column($empire['units'], 'code')
        );
        $this->assertSame([240, 12, 1], array_column($empire['units'], 'factor'));
        $this->assertSame(['ecu', 'denier'], array_column($bretonnia['units'], 'code'));
        $this->assertSame([240, 1], array_column($bretonnia['units'], 'factor'));
    }

    public function testCthulhuCurrenciesSupport1920sDollarAndPreDecimalPound(): void
    {
        $currencies = (new ShopCurrencyService())->definitionsForSystem('coc7e');

        $usd = $this->currencyByCode($currencies, 'coc_usd_1920');
        $gbp = $this->currencyByCode($currencies, 'coc_gbp_1920');

        $this->assertSame(['dollar', 'cent'], array_column($usd['units'], 'code'));
        $this->assertSame([100, 1], array_column($usd['units'], 'factor'));
        $this->assertSame(['pound', 'shilling', 'penny'], array_column($gbp['units'], 'code'));
        $this->assertSame([240, 12, 1], array_column($gbp['units'], 'factor'));
    }

    private function currencyByCode(array $currencies, string $code): array
    {
        foreach ($currencies as $currency) {
            if (($currency['code'] ?? null) === $code) {
                return $currency;
            }
        }

        $this->fail('Currency not found: '.$code);
    }
}
