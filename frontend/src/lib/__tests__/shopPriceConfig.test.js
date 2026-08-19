import { describe, expect, it } from "vitest";
import {
  applyShopPricingPreset,
  createDefaultShopPricingConfig,
  normalizeShopPricingConfig,
} from "@/lib/trade/shopPriceCalculator";

describe("shopPriceConfig", () => {
  it("normalizes legacy profiles to the current safe contract", () => {
    const result = normalizeShopPricingConfig({
      version: 2,
      guardrails: { maxBuybackRatio: 4 },
    });
    expect(result.version).toBe(4);
    expect(result.currencyPolicy.paymentExchangeFeePercent).toBe(5);
    expect(result.guardrails.maxBuybackRatio).toBe(1);
    expect(result.enabledModifiers.marketEvents).toBe(true);
  });

  it("keeps campaign currency settings while applying a preset", () => {
    const current = createDefaultShopPricingConfig();
    current.currencyPolicy.settlementCurrencyCode = "silver";
    current.currencyPolicy.exchangeRates.gold = 20;
    const result = applyShopPricingPreset("friendly", current);
    expect(result.baseMultipliers).toEqual({ buy: 0.9, sell: 0.68 });
    expect(result.currencyPolicy.exchangeRates.gold).toBe(20);
  });
});
