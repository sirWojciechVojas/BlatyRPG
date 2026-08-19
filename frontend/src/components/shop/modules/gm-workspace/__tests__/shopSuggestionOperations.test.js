import { reactive, ref } from "vue";
import { describe, expect, it, vi } from "vitest";
import { installWorkspaceGroup6 } from "@/components/shop/modules/gm-workspace/composables/groups/group6";

const createDeferred = () => {
  let resolve;
  const promise = new Promise((done) => {
    resolve = done;
  });
  return { promise, resolve };
};

const createDeps = (dispatch) => ({
  store: { dispatch },
  defaultCurrencyCode: ref("generic"),
  activeShopId: ref(7),
  activeShop: ref({ id: 7, ownerCode: "GM" }),
  activeDraft: ref(true),
  actorOptions: ref([]),
  shopState: ref({ shopProfiles: {} }),
  profileDraft: reactive({ ownerCode: "GM" }),
  suggestionOperations: reactive({}),
  addingAllSuggestions: ref(false),
  shopSuggestions: ref([]),
  suggestionsOpen: ref(true),
  shopSubtab: ref("offer"),
  t: (key) => key,
});

describe("GM workspace suggestion operations", () => {
  it("materializes different suggestions concurrently and refreshes once when idle", async () => {
    const first = createDeferred();
    const second = createDeferred();
    const dispatch = vi.fn((type, payload) => {
      if (type === "shop/materializeShopSuggestion") {
        return payload.suggestionId === "draft:1"
          ? first.promise
          : second.promise;
      }
      return Promise.resolve({ ok: true });
    });
    const deps = createDeps(dispatch);
    installWorkspaceGroup6(deps);

    const firstOperation = deps.applySingleSuggestion({
      suggestionId: "draft:1",
      action: "create_draft",
    });
    const secondOperation = deps.applySingleSuggestion({
      suggestionId: "draft:2",
      action: "create_draft",
    });

    expect(dispatch).toHaveBeenCalledTimes(2);
    expect(Object.keys(deps.suggestionOperations)).toEqual([
      "draft:1",
      "draft:2",
    ]);
    expect(dispatch).toHaveBeenNthCalledWith(
      1,
      "shop/materializeShopSuggestion",
      expect.objectContaining({
        suggestionId: "draft:1",
        mode: "template_plus_item",
        refresh: false,
      }),
    );

    first.resolve({ created: 1, applied: 1 });
    await firstOperation;
    expect(
      dispatch.mock.calls.filter(([type]) => type === "shop/loadTradingData"),
    ).toHaveLength(0);

    second.resolve({ created: 1, applied: 1 });
    await secondOperation;
    await Promise.resolve();
    expect(
      dispatch.mock.calls.filter(([type]) => type === "shop/loadTradingData"),
    ).toHaveLength(1);
  });

  it("supports template-only materialization before adding an instance", async () => {
    const dispatch = vi.fn(async () => ({ created: 1, applied: 0 }));
    const deps = createDeps(dispatch);
    installWorkspaceGroup6(deps);

    await deps.createSuggestionTemplate({
      suggestionId: "draft:3",
      action: "create_draft",
    });

    expect(dispatch).toHaveBeenCalledWith(
      "shop/materializeShopSuggestion",
      expect.objectContaining({
        suggestionId: "draft:3",
        mode: "template_only",
        refresh: false,
      }),
    );
  });

  it("adds all suggestions with one bulk request", async () => {
    const dispatch = vi.fn(async () => 2);
    const deps = createDeps(dispatch);
    deps.shopSuggestions.value = [
      { suggestionId: "draft:1" },
      { suggestionId: "template:8", templateId: 8 },
    ];
    installWorkspaceGroup6(deps);

    const applied = await deps.applyAllSuggestions();

    expect(applied).toBe(2);
    expect(dispatch).toHaveBeenCalledWith("shop/applyShopSuggestions", {
      shopId: 7,
      suggestionIds: ["draft:1", "template:8"],
      replaceExisting: false,
      refresh: false,
    });
  });
});
