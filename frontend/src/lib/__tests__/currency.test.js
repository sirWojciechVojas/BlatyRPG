import { describe, expect, it } from "vitest";
import {
  COC_CURRENCY_DEFINITIONS,
  WFRP_CURRENCY_DEFINITIONS,
  composeCurrencyAmount,
  decomposeCurrencyAmount,
  formatCurrencyAmount,
  resolveCurrencyDefinition,
  resolveDisplayCurrencyCode,
} from "@/lib/trade/currency";

describe("shop currency model", () => {
  it("decomposes imperial prices into crowns, shillings and pennies", () => {
    const empire = resolveCurrencyDefinition(
      WFRP_CURRENCY_DEFINITIONS,
      "wfrp_empire",
    );

    expect(decomposeCurrencyAmount(1927, empire)).toEqual({
      gold_crown: 8,
      silver_shilling: 0,
      brass_penny: 7,
    });
    expect(formatCurrencyAmount(1927, empire, "pl")).toBe("8 zk 0 s 7 p");
  });

  it("round-trips Bretonnian ecu and denier through the canonical value", () => {
    const bretonnia = resolveCurrencyDefinition(
      WFRP_CURRENCY_DEFINITIONS,
      "wfrp_bretonnia",
    );
    const total = composeCurrencyAmount({ ecu: 3, denier: 17 }, bretonnia);

    expect(total).toBe(737);
    expect(decomposeCurrencyAmount(total, bretonnia)).toEqual({
      ecu: 3,
      denier: 17,
    });
    expect(formatCurrencyAmount(total, bretonnia, "pl")).toBe("3 ecu 17 d");
  });

  it("supports 1920s dollars and pre-decimal pounds for Cthulhu games", () => {
    const usd = resolveCurrencyDefinition(
      COC_CURRENCY_DEFINITIONS,
      "coc_usd_1920",
    );
    const gbp = resolveCurrencyDefinition(
      COC_CURRENCY_DEFINITIONS,
      "coc_gbp_1920",
    );

    expect(formatCurrencyAmount(1234, usd, "en")).toBe("12 $ 34 ¢");
    expect(formatCurrencyAmount(267, gbp, "en")).toBe("1 £ 2 s 3 d");
  });

  it("treats a legacy generic code as the campaign currency", () => {
    expect(resolveDisplayCurrencyCode("generic", "wfrp_empire")).toBe(
      "wfrp_empire",
    );
    expect(resolveDisplayCurrencyCode("", "coc_usd_1920")).toBe("coc_usd_1920");
    expect(resolveDisplayCurrencyCode("wfrp_bretonnia", "wfrp_empire")).toBe(
      "wfrp_bretonnia",
    );
  });
});
