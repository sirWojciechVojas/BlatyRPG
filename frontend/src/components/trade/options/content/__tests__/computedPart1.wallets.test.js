import { describe, expect, it } from "vitest";
import { createContentComputedPart1 } from "../computedPart1";

describe("trade wallet display", () => {
  it("shows the zero settlement purse first and hides unrelated empty purses", () => {
    const computed = createContentComputedPart1();
    const vm = {
      activeSettlementCurrencyCode: "wfrp_bretonnia",
      currencyDefinitions: {
        defaultCurrencyCode: "wfrp_empire",
        currencies: [
          { code: "wfrp_empire", labelPl: "Imperium" },
          { code: "wfrp_bretonnia", labelPl: "Bretonnia" },
          { code: "generic", labelPl: "Inna" },
        ],
      },
      walletBalances: { wfrp_empire: 120, wfrp_bretonnia: 0, generic: 0 },
      bgWalletBrass: 0,
      $i18n: { locale: "pl" },
    };

    const wallets = computed.visiblePlayerWallets.call(vm);

    expect(wallets.map((wallet) => wallet.currencyCode)).toEqual([
      "wfrp_bretonnia",
      "wfrp_empire",
    ]);
    expect(wallets[0]).toMatchObject({
      balance: 0,
      isSettlementCurrency: true,
    });
  });
});
