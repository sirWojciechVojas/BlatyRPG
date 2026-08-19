import { describe, expect, it } from "vitest";
import shopModule from "../shop";
import { GM_MODES } from "../../../lib/trade/constants";
import { aggregateTradeItems } from "../shop/runtime";

const buildState = () => {
  const state = shopModule.state();
  state.inventoryItems = [
    {
      ID: 1,
      INV_ID: 101,
      OWNER_OPT: "DEFAULT",
      OWNER: "DEFAULT",
      QUANTITY: 1,
      PERSONAL_PSEU: "",
      PERSONAL_DESC: "",
      PERSONAL_COST: 0,
      NAME: "Default item",
      DESCRIPTION: "",
      IMG_CLASS: "v0001",
      PRIZE: 0,
      CHARGE: 0,
    },
    {
      ID: 2,
      INV_ID: 102,
      OWNER_OPT: "DEFAULT",
      OWNER: "BG1",
      QUANTITY: 1,
      PERSONAL_PSEU: "",
      PERSONAL_DESC: "",
      PERSONAL_COST: 0,
      NAME: "BG item",
      DESCRIPTION: "",
      IMG_CLASS: "v0001",
      PRIZE: 0,
      CHARGE: 0,
    },
    {
      ID: 3,
      INV_ID: 103,
      OWNER_OPT: "TRASH",
      OWNER: "BG1",
      QUANTITY: 1,
      PERSONAL_PSEU: "",
      PERSONAL_DESC: "",
      PERSONAL_COST: 0,
      NAME: "Trash item",
      DESCRIPTION: "",
      IMG_CLASS: "v0001",
      PRIZE: 0,
      CHARGE: 0,
    },
  ];
  state.trashItems = [
    {
      ID: 200,
      INV_ID: 103,
      OWNER_OPT: "TRASH",
      OWNER: "BG1",
      QUANTITY: 1,
      PERSONAL_PSEU: "",
      PERSONAL_DESC: "",
      PERSONAL_COST: 0,
      NAME: "Trash bucket item",
      DESCRIPTION: "",
      IMG_CLASS: "v0001",
      PRIZE: 0,
      CHARGE: 0,
    },
  ];
  return state;
};

describe("shop getters - inventory mode sell items", () => {
  it("returns only non-trash inventory items for GM inventory mode", () => {
    const state = buildState();
    state.isGM = true;
    state.gmMode = GM_MODES.INVENTORY;

    const result = shopModule.getters.sellItems(state);
    const ids = result.map((item) => Number(item.ID)).sort((a, b) => a - b);

    expect(ids).toEqual([1, 2]);
  });

  it("keeps previous behavior for GM trash mode", () => {
    const state = buildState();
    state.isGM = true;
    state.gmMode = GM_MODES.TRASH;

    const result = shopModule.getters.sellItems(state);
    expect(result.map((item) => Number(item.ID))).toEqual([200]);
  });

  it("keeps previous behavior for GM templates mode", () => {
    const state = buildState();
    state.isGM = true;
    state.gmMode = GM_MODES.TEMPLATES;

    const result = shopModule.getters.sellItems(state);
    expect(result).toEqual([]);
  });
});

describe("shop trade item aggregation", () => {
  it("combines identical instances and keeps their source ids", () => {
    const base = {
      INV_ID: 101,
      NAME: "Rope",
      PERSONAL_PSEU: "Rope",
      PERSONAL_DESC: "Ten yards",
      QUANTITY: 1,
      PERSONAL_COST: 12,
      CURRENCY: "wfrp_empire",
      CHARGE: 1,
      INSTANCE_META: {},
    };

    const result = aggregateTradeItems([
      { ...base, ID: 11 },
      { ...base, ID: 12 },
    ]);

    expect(result).toHaveLength(1);
    expect(result[0].QUANTITY).toBe(2);
    expect(result[0].AGGREGATED_ITEM_IDS).toEqual([11, 12]);
    expect(result[0].IS_ITEM_INSTANCE).toBe(true);
  });

  it("does not combine differently personalized instances", () => {
    const result = aggregateTradeItems([
      { ID: 11, INV_ID: 101, NAME: "Rope", QUANTITY: 1 },
      { ID: 12, INV_ID: 101, NAME: "Silk rope", QUANTITY: 1 },
    ]);

    expect(result).toHaveLength(2);
  });
});

describe("shop mutations - independent trade selections", () => {
  it("clears only buy-side selection", () => {
    const state = shopModule.state();
    state.selectedBuyIds = [10];
    state.selectedSellIds = [20];
    state.selectedBuyQuantities = { 10: 2 };
    state.selectedSellQuantities = { 20: 3 };

    shopModule.mutations.clearBuySelection(state);

    expect(state.selectedBuyIds).toEqual([]);
    expect(state.selectedBuyQuantities).toEqual({});
    expect(state.selectedSellIds).toEqual([20]);
    expect(state.selectedSellQuantities).toEqual({ 20: 3 });
  });

  it("clears only sell-side selection", () => {
    const state = shopModule.state();
    state.selectedBuyIds = [10];
    state.selectedSellIds = [20];
    state.selectedBuyQuantities = { 10: 2 };
    state.selectedSellQuantities = { 20: 3 };

    shopModule.mutations.clearSellSelection(state);

    expect(state.selectedBuyIds).toEqual([10]);
    expect(state.selectedBuyQuantities).toEqual({ 10: 2 });
    expect(state.selectedSellIds).toEqual([]);
    expect(state.selectedSellQuantities).toEqual({});
  });
});

describe("shop session and permissions", () => {
  it("treats an already loaded matching cache as a successful load", async () => {
    const state = shopModule.state();
    state.tradeDataLoaded = true;
    state.isGM = true;
    state.gmMode = GM_MODES.TEMPLATES;
    state.tradeDataCacheKey = "1|BG1|none|gm:templates";
    const commit = (type, payload) =>
      shopModule.mutations[type](state, payload);

    const result = await shopModule.actions.loadTradingData(
      { state, commit, dispatch: () => Promise.resolve() },
      { campaignId: 1, ownerCode: "BG1" },
    );

    expect(result).toEqual({ ok: true, cached: true });
  });

  it("does not enable GM mode without a GM permission from bootstrap", () => {
    const state = shopModule.state();

    shopModule.mutations.setIsGM(state, true);

    expect(state.isGM).toBe(false);
  });

  it("enables GM mode only after bootstrap grants the permission", () => {
    const state = shopModule.state();
    shopModule.mutations.setShopSession(state, {
      context: { campaignId: 7, ownerCode: "PC-ALICE" },
      actors: [{ id: 4, ownerCode: "PC-ALICE", name: "Alice" }],
      permissions: { isGm: true, ownerCodes: ["PC-ALICE"] },
    });

    shopModule.mutations.setIsGM(state, true);

    expect(state.isGM).toBe(true);
    expect(state.campaignId).toBe(7);
    expect(state.actors).toHaveLength(1);
  });

  it("keeps GM authorization while showing the selected character's regular shop", () => {
    const state = shopModule.state();
    shopModule.mutations.setShopSession(state, {
      context: { campaignId: 7, ownerCode: "PC-ALICE" },
      actors: [{ id: 4, ownerCode: "PC-ALICE", name: "Alice" }],
      permissions: {
        isGm: true,
        canManageShops: true,
        canTrade: true,
        ownerCodes: ["PC-ALICE"],
      },
    });
    shopModule.mutations.setIsGM(state, true);

    shopModule.mutations.enterCharacterShoppingMode(state);

    expect(state.isGM).toBe(false);
    expect(state.permissions.isGm).toBe(true);
    expect(state.permissions.canManageShops).toBe(true);
    expect(state.context.ownerCode).toBe("PC-ALICE");
    expect(shopModule.getters.sellListTitle(state)).toContain("Alice");
  });

  it("limits a player's sell list to the owner code from bootstrap", () => {
    const state = buildState();
    state.context = { ownerCode: "PC-ALICE" };
    state.inventoryItems.push({
      ...state.inventoryItems[1],
      ID: 4,
      OWNER: "PC-ALICE",
      OWNER_OPT: "PC-ALICE",
      NAME: "Alice item",
    });

    const result = shopModule.getters.sellItems(state);

    expect(result.map((item) => Number(item.ID))).toEqual([4]);
  });

  it("replaces the previous character inventory when another character is loaded", () => {
    const state = buildState();
    expect(state.inventoryItems.some((item) => Number(item.ID) === 2)).toBe(
      true,
    );

    shopModule.mutations.setTradingData(state, {
      shops: [],
      templateItems: [],
      inventoryItems: [
        {
          ID: 88,
          INV_ID: 12,
          NAME: "BG2 item",
          OWNER: "BG2",
          OWNER_OPT: "BG2",
          QUANTITY: 1,
        },
      ],
      trashItems: [],
      walletBalances: { generic: 25 },
      walletCurrencyCode: "generic",
    });

    expect(state.inventoryItems).toHaveLength(1);
    expect(state.inventoryItems[0]).toMatchObject({
      ID: 88,
      OWNER: "BG2",
      OWNER_OPT: "BG2",
    });
    expect(state.inventoryItems.some((item) => item.OWNER_OPT === "BG1")).toBe(
      false,
    );
  });

  it("normalizes the server container state without inventing ids", () => {
    const state = shopModule.state();
    shopModule.mutations.setContainerState(state, {
      containers: [{ id: 41, container_type: "CHARACTER" }],
      templateRows: [{ id: 51, container_id: 41, template_id: 9 }],
      instanceRows: [{ id: 61, container_id: 41, instance_id: 12 }],
      itemInstances: [{ id: 12, template_id: 9 }],
    });

    expect(state.containerState.containers[0].id).toBe(41);
    expect(state.containerState.templateRows[0].id).toBe(51);
    expect(state.containerState.instanceRows[0].id).toBe(61);
    expect(state.containerState.itemInstances[0].id).toBe(12);
  });
});
