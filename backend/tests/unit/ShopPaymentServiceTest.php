<?php

use App\Services\Shop\ShopPaymentService;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ShopPaymentServiceTest extends CIUnitTestCase
{
    private ShopPaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ShopPaymentService();
    }

    public function testSettlementCurrencyIsUsedFirstWithoutConversion(): void
    {
        $quote = $this->quote(100, ['wfrp_empire' => 150, 'wfrp_bretonnia' => 500]);
        $this->assertTrue($quote['canPay']);
        $this->assertFalse($quote['requiresConversion']);
        $this->assertSame(100, $quote['settlementDebit']);
        $this->assertCount(1, $quote['debits']);
    }

    public function testFivePercentFeeRoundsForeignDebitUp(): void
    {
        $quote = $this->quote(101, ['wfrp_empire' => 0, 'wfrp_bretonnia' => 500]);
        $this->assertTrue($quote['canPay']);
        $this->assertSame(107, $quote['foreignWallets'][0]['debit']);
        $this->assertSame(101, $quote['foreignWallets'][0]['settlementCovered']);
    }

    public function testSeveralCurrenciesAreOrderedByGreatestNetCoverage(): void
    {
        $quote = $this->service->quote(
            1,
            'CHAR_1',
            250,
            'wfrp_empire',
            [
                'paymentExchangeFeePercent' => 5,
                'exchangeRates' => ['wfrp_bretonnia' => 1, 'generic' => 2],
            ],
            ['wfrp_empire' => 50, 'wfrp_bretonnia' => 100, 'generic' => 100]
        );
        $this->assertTrue($quote['canPay']);
        $this->assertSame('generic', $quote['foreignWallets'][0]['currencyCode']);
        $this->assertSame(50, $quote['settlementDebit']);
        $this->assertGreaterThan(0, $quote['foreignWallets'][0]['debit']);
    }

    public function testMissingRateAndDisabledWalletCannotCoverPrice(): void
    {
        $quote = $this->service->quote(
            1,
            'CHAR_1',
            100,
            'wfrp_empire',
            ['paymentExchangeFeePercent' => 5, 'exchangeRates' => []],
            ['wfrp_empire' => 0, 'wfrp_bretonnia' => 1000]
        );
        $this->assertFalse($quote['canPay']);
        $this->assertSame([], $quote['foreignWallets']);
    }

    public function testSelectionChangesFingerprintAndAvailableCoverage(): void
    {
        $all = $this->quote(100, ['wfrp_empire' => 0, 'wfrp_bretonnia' => 200]);
        $none = $this->service->quote(
            1,
            'CHAR_1',
            100,
            'wfrp_empire',
            ['paymentExchangeFeePercent' => 5, 'exchangeRates' => ['wfrp_bretonnia' => 1]],
            ['wfrp_empire' => 0, 'wfrp_bretonnia' => 200],
            []
        );
        $this->assertTrue($all['canPay']);
        $this->assertFalse($none['canPay']);
        $this->assertNotSame($all['quoteFingerprint'], $none['quoteFingerprint']);
    }

    private function quote(int $price, array $balances): array
    {
        return $this->service->quote(
            1,
            'CHAR_1',
            $price,
            'wfrp_empire',
            [
                'paymentExchangeFeePercent' => 5,
                'exchangeRates' => ['wfrp_bretonnia' => 1],
            ],
            $balances
        );
    }
}
