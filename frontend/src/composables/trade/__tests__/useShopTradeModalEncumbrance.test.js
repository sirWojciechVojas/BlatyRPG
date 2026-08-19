import { reactive } from "vue";
import { describe, expect, it } from "vitest";
import { useShopTradeModalEncumbrance } from "@/composables/trade/useShopTradeModalEncumbrance";

describe("character shop encumbrance", () => {
  it("calculates the load for the character selected by GM", () => {
    const state = reactive({});
    const deps = {
      isGM: false,
      activeBgOwner: "BG2",
      inventoryItems: [
        { OWNER_OPT: "BG1", CHARGE: 10, QUANTITY: 2 },
        { OWNER_OPT: "BG2", CHARGE: 7, QUANTITY: 3 },
        { OWNER: "BG2", CHARGE: 4, QUANTITY: 1 },
      ],
      selectedBuyIds: [],
      selectedBuyQuantities: {},
      buyItems: [],
    };

    const modal = useShopTradeModalEncumbrance({ state }, deps);

    expect(modal.bgEncumbranceCurrent.value).toBe(25);
  });
});
