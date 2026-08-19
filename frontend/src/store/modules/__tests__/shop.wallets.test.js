import { describe, expect, it } from "vitest";
import { createTradeMutations } from "../shop/trade";

const createState = () => ({
  activeShopId: 1,
  bgWalletBrass: 0,
  walletBalances: {},
  walletCurrencyCode: "wfrp_empire",
  currencyDefinitions: { defaultCurrencyCode: "wfrp_empire" },
  shopProfiles: {
    1: {
      pricingConfig: {
        currencyPolicy: { settlementCurrencyCode: "wfrp_bretonnia" },
      },
    },
  },
});

describe("shop wallet balances", () => {
  it("keeps independent balances for every currency", () => {
    const state = createState();
    const mutations = createTradeMutations();

    mutations.setWalletBalances(state, [
      { currencyCode: "wfrp_empire", balance: 820 },
      { currencyCode: "wfrp_bretonnia", balance: 480 },
    ]);
    mutations.adjustWalletBrass(state, -120);

    expect(state.walletBalances).toEqual({
      wfrp_empire: 820,
      wfrp_bretonnia: 360,
    });
    expect(state.bgWalletBrass).toBe(360);
  });

  it("adds a newly acquired foreign-currency purse", () => {
    const state = createState();
    const mutations = createTradeMutations();

    mutations.setWalletBalances(state, { wfrp_empire: 820 });
    mutations.setWalletBalance(state, {
      currencyCode: "wfrp_bretonnia",
      balance: 240,
    });

    expect(state.walletBalances.wfrp_empire).toBe(820);
    expect(state.walletBalances.wfrp_bretonnia).toBe(240);
    expect(state.bgWalletBrass).toBe(240);
  });
});
