import { describe, expect, it } from "vitest";
import shop from "../shop";

describe("shop creation", () => {
  it("initializes a missing shop profile during the first data load", () => {
    const state = shop.state();

    shop.mutations.setTradingData(state, {
      shops: [
        {
          id: 7,
          name: "Pod Czarnym Młotem",
          typeId: "platnerz",
          isActive: true,
          shopEntries: [],
        },
      ],
      activeShopId: 7,
      shopProfiles: {},
      templateItems: [],
      inventoryItems: [],
      trashItems: [],
    });

    expect(state.activeShopId).toBe(7);
    expect(state.shopProfiles[7]).toMatchObject({
      signboardName: "Pod Czarnym Młotem",
      typeId: "platnerz",
    });
  });

  it("keeps the selected business type in the fallback profile", () => {
    const state = shop.state();

    shop.mutations.createShop(state, {
      name: "Pod Czarnym Młotem",
      typeId: "platnerz",
      ownerCode: "NPC",
      ownerName: "Mistrz Ortwin",
    });

    const profile = state.shopProfiles[state.activeShopId];
    expect(profile.signboardName).toBe("Pod Czarnym Młotem");
    expect(profile.typeId).toBe("platnerz");
  });
});
