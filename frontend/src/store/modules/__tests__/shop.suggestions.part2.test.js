import { describe, expect, it } from "vitest";
import shopModule from "../shop";
const baseTemplate = {
  ID: 8,
  NAME: "Eliksir leczenia",
  DESCRIPTION: "Leczaca mikstura z cechu alchemikow.",
  DETAILS: "Test",
  ITEM_CLASS: "ALCHEMY",
  ITEM_ID: "112",
  ITEM_GENRE: "HEALING",
  IMG_CLASS: "v1089",
  PRIZE: 960,
  CHARGE: 25,
};
const createHarness = () => {
  const state = shopModule.state();
  state.templateItems = [
    {
      ...baseTemplate,
    },
  ];
  state.shops = [
    {
      id: 1,
      name: "Test shop",
      shopEntries: [],
      items: [],
      itemIds: [],
      assortment: {
        DEFAULT: [],
        TRASH: [],
        BG1: [],
        BG2: [],
      },
    },
  ];
  state.activeShopId = 1;
  state.shopName = "Test shop";
  state.shopEditorState = {
    ...state.shopEditorState,
    selectedSuggestionIds: [],
  };
  const ctx = {
    state,
    commit(type, payload) {
      const mutation = shopModule.mutations[type];
      if (!mutation) {
        throw new Error(`Missing mutation: ${type}`);
      }
      mutation(state, payload);
    },
    dispatch: async () => null,
  };
  ctx.dispatch = async (type, payload) => {
    const action = shopModule.actions[type];
    if (!action) {
      throw new Error(`Missing action: ${type}`);
    }
    return action(ctx, payload);
  };
  return {
    state,
    ctx,
  };
};
const draftSuggestion = {
  suggestionId: "draft:100:0",
  action: "create_draft",
  quantity: 1,
  draftTemplate: {
    ID: 100,
    NAME: "Tonik odswiezajacy",
    DESCRIPTION: "Tonik na oslabienie.",
    DETAILS: "AUTO_DRAFT",
    ITEM_CLASS: "POTION",
    ITEM_ID: "",
    ITEM_GENRE: "HEALING",
    IMG_CLASS: "v1089",
    PRIZE: 700,
    CHARGE: 15,
    DRAFT: true,
  },
  personalizedVariants: [
    {
      variantId: "draft:100:0:v1",
      personalPseu: "Wersja Cechowa",
      personalDesc: "Partia cechowa.",
      personalCost: 710,
      quantity: 1,
    },
    {
      variantId: "draft:100:0:v2",
      personalPseu: "Wersja Miejska",
      personalDesc: "Partia miejska.",
      personalCost: 730,
      quantity: 1,
    },
  ],
};

describe("shop module suggestion materialization", () => {
  it("template_plus_item from recommendation draft creates template and item", async () => {
    const { state, ctx } = createHarness();
    state.shopSuggestions = [];
    state.shopTemplateRecommendations = [
      {
        ...draftSuggestion,
      },
    ];
    const result = await shopModule.actions.materializeShopSuggestion(ctx, {
      shopId: 1,
      suggestionId: draftSuggestion.suggestionId,
      mode: "template_plus_item",
      variantId: "draft:100:0:v2",
    });
    expect(result.created).toBe(1);
    expect(result.applied).toBe(1);
    expect(
      state.templateItems.some((entry) => entry.NAME === "Tonik odswiezajacy"),
    ).toBe(true);
    expect(state.shops[0].shopEntries.length).toBe(1);
    expect(state.shops[0].shopEntries[0].PERSONAL_PSEU).toBe("Wersja Miejska");
    expect(state.shopTemplateRecommendations.length).toBe(1);
    expect(state.shopTemplateRecommendations[0].action).toBe("use_existing");
    expect(
      Number.isFinite(Number(state.shopTemplateRecommendations[0].templateId)),
    ).toBe(true);
  });
  it("applyShopSuggestions expands variants for bulk quantity without collapsing personalized entries", async () => {
    const { state, ctx } = createHarness();
    state.shopSuggestions = [
      {
        suggestionId: "template:8",
        action: "use_existing",
        templateId: 8,
        quantity: 3,
        personalizedVariants: [
          {
            variantId: "template:8:v1",
            personalPseu: "Wariant A",
            personalDesc: "Opis A",
            personalCost: 910,
            quantity: 1,
          },
          {
            variantId: "template:8:v2",
            personalPseu: "Wariant B",
            personalDesc: "Opis B",
            personalCost: 920,
            quantity: 1,
          },
        ],
      },
    ];
    const applied = await shopModule.actions.applyShopSuggestions(ctx, {
      shopId: 1,
      suggestionIds: ["template:8"],
    });
    expect(applied).toBe(3);
    expect(state.shops[0].shopEntries.length).toBe(3);
    expect(
      state.shops[0].shopEntries.map((entry) => entry.PERSONAL_PSEU),
    ).toEqual(["Wariant A", "Wariant B", "Wariant A"]);
  });
  it("rollShopAssortment supports dry-run without mutating shop entries", async () => {
    const { state, ctx } = createHarness();
    state.shops[0].shopEntries = [
      {
        INV_ID: 8,
        SLOT: "STOISKO",
        PERSONAL_PSEU: "Istniejacy",
        PERSONAL_DESC: "Pozycja bazowa",
        PERSONAL_COST: 950,
        QUANTITY: 1,
        OWNER_OPT: "DEFAULT",
        OWNER: "BG1",
      },
    ];
    state.shopProfiles = {
      1: {
        wealthTier: "standard",
        reputation: "neutralna",
        seasonality: "caloroczny",
      },
    };
    const startEntries = [...state.shops[0].shopEntries];
    const generatedSuggestion = {
      suggestionId: "template:8",
      action: "use_existing",
      templateId: 8,
      quantity: 1,
      score: 5,
      personalizedVariants: [
        {
          variantId: "template:8:v1",
          personalPseu: "Rolowany",
          personalDesc: "Wynik podgladu",
          personalCost: 980,
          quantity: 1,
        },
      ],
    };
    const forwarded = [];
    const originalDispatch = ctx.dispatch;
    ctx.dispatch = async (type, payload) => {
      if (type === "generateShopSuggestions") {
        return [generatedSuggestion];
      }
      if (type === "applyShopSuggestions") {
        forwarded.push(payload);
        return 0;
      }
      return originalDispatch(type, payload);
    };
    const result = await shopModule.actions.rollShopAssortment(ctx, {
      shopId: 1,
      dryRun: true,
      clearExisting: false,
      targetInstances: 8,
      uniqueItems: 1,
    });
    expect(result.appliedUnique).toBeGreaterThan(0);
    expect(result.appliedInstances).toBeGreaterThan(0);
    expect(result.suggestions.length).toBeGreaterThan(0);
    expect(forwarded).toHaveLength(0);
    expect(state.shops[0].shopEntries).toEqual(startEntries);
  });
});
