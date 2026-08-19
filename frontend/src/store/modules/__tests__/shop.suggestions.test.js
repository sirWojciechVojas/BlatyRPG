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
  it("materializeShopSuggestion(template_only) creates template only", async () => {
    const { state, ctx } = createHarness();
    state.shopSuggestions = [
      {
        ...draftSuggestion,
      },
    ];
    const result = await shopModule.actions.materializeShopSuggestion(ctx, {
      shopId: 1,
      suggestionId: draftSuggestion.suggestionId,
      mode: "template_only",
      variantId: "draft:100:0:v1",
    });
    expect(result.created).toBe(1);
    expect(result.applied).toBe(0);
    expect(
      state.templateItems.some((entry) => entry.NAME === "Tonik odswiezajacy"),
    ).toBe(true);
    expect(state.shops[0].shopEntries.length).toBe(0);
  });
  it("materializeShopSuggestion(template_plus_item) creates template and personalized shop entry", async () => {
    const { state, ctx } = createHarness();
    state.shopSuggestions = [
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
    expect(state.shops[0].shopEntries.length).toBe(1);
    expect(state.shops[0].shopEntries[0].PERSONAL_PSEU).toBe("Wersja Miejska");
    expect(state.shops[0].shopEntries[0].PERSONAL_DESC).toBe("Partia miejska.");
    expect(state.shops[0].shopEntries[0].PERSONAL_COST).toBe(730);
  });
  it("materializeShopSuggestion(item_only) for existing template adds personalized entry only", async () => {
    const { state, ctx } = createHarness();
    state.shopSuggestions = [
      {
        suggestionId: "template:8",
        action: "use_existing",
        templateId: 8,
        quantity: 1,
        personalizedVariants: [
          {
            variantId: "template:8:v1",
            personalPseu: "Partia 1",
            personalDesc: "Opis 1",
            personalCost: 950,
            quantity: 1,
          },
          {
            variantId: "template:8:v2",
            personalPseu: "Partia 2",
            personalDesc: "Opis 2",
            personalCost: 980,
            quantity: 1,
          },
        ],
      },
    ];
    const result = await shopModule.actions.materializeShopSuggestion(ctx, {
      shopId: 1,
      suggestionId: "template:8",
      mode: "item_only",
      variantId: "template:8:v2",
    });
    expect(result.created).toBe(0);
    expect(result.applied).toBe(1);
    expect(state.templateItems.length).toBe(1);
    expect(state.shops[0].shopEntries.length).toBe(1);
    expect(state.shops[0].shopEntries[0].PERSONAL_PSEU).toBe("Partia 2");
  });
  it("promoteRecommendationsToSuggestions appends next 30 recommendations without duplicates", async () => {
    const { state, ctx } = createHarness();
    state.shopSuggestions = [
      {
        suggestionId: "template:8",
        action: "use_existing",
        templateId: 8,
        scoreRaw: 320.12,
        score: 320.12,
        scoreTieBreaker: 0.12,
        label: "Eliksir leczenia",
      },
    ];
    state.shopTemplateRecommendations = [
      {
        suggestionId: "template:8",
        action: "use_existing",
        templateId: 8,
        scoreRaw: 320.12,
        score: 320.12,
        scoreTieBreaker: 0.12,
        label: "Eliksir leczenia",
      },
      ...Array.from(
        {
          length: 45,
        },
        (_, index) => ({
          suggestionId: `template:${900 + index}`,
          action: "use_existing",
          templateId: 900 + index,
          scoreRaw: 260 - index * 1.1,
          score: Number((260 - index * 1.1).toFixed(2)),
          scoreTieBreaker: Number((0.97 - index * 0.01).toFixed(2)),
          label: `Rekomendacja ${index + 1}`,
        }),
      ),
    ];
    const promoted =
      await shopModule.actions.promoteRecommendationsToSuggestions(ctx, {
        count: 30,
      });
    expect(promoted).toBe(30);
    expect(state.shopSuggestions.length).toBe(31);
    const ids = state.shopSuggestions.map((entry) => entry.suggestionId);
    expect(new Set(ids).size).toBe(ids.length);
  });
  it("materializeShopSuggestion resolves entry from remaining recommendations (item_only)", async () => {
    const { state, ctx } = createHarness();
    state.shopSuggestions = [];
    state.shopTemplateRecommendations = [
      {
        suggestionId: "template:8",
        action: "use_existing",
        templateId: 8,
        quantity: 1,
        scoreRaw: 211.42,
        score: 211.42,
        scoreTieBreaker: 0.42,
        label: "Eliksir leczenia",
        personalizedVariants: [
          {
            variantId: "template:8:v1",
            personalPseu: "Eliksir specjalny",
            personalDesc: "Wariant rekomendacji.",
            personalCost: 1110,
            quantity: 1,
          },
        ],
      },
    ];
    const result = await shopModule.actions.materializeShopSuggestion(ctx, {
      shopId: 1,
      suggestionId: "template:8",
      mode: "item_only",
      variantId: "template:8:v1",
    });
    expect(result.created).toBe(0);
    expect(result.applied).toBe(1);
    expect(state.shops[0].shopEntries.length).toBe(1);
    expect(state.shops[0].shopEntries[0].PERSONAL_PSEU).toBe(
      "Eliksir specjalny",
    );
  });
});
