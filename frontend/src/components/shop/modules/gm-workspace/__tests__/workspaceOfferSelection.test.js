import { describe, expect, it, vi } from "vitest";
import { installWorkspaceGroup7 } from "@/components/shop/modules/gm-workspace/composables/groups/group7";

const createDeps = () => ({
  offerSelection: [],
  filteredOfferKeys: { value: ["offer:1", "offer:2"] },
  allFilteredOfferSelected: { value: false },
  groupedOffer: {
    value: [
      { OFFER_KEY: "offer:1", INSTANCE_IDS: [10, 11] },
      { OFFER_KEY: "offer:2", INSTANCE_IDS: [12] },
    ],
  },
  allItemInstances: {
    value: [
      { ID: 10, CONTAINER_ID: 5 },
      { ID: 11, CONTAINER_ID: 5 },
      { ID: 12, CONTAINER_ID: 5 },
    ],
  },
  offerTargetId: { value: null },
  offerSelectionBusy: { value: false },
  profileDraft: { ownerCode: "BG1" },
  shopState: {
    value: {
      campaignId: 1,
      containerState: {
        containers: [
          {
            id: 9,
            container_type: "TRASH",
            owner_code: "BG1",
            system_key: "OWNER_TRASH",
          },
        ],
      },
    },
  },
  store: {
    dispatch: vi.fn(async (action) =>
      action === "shop/moveContainerItems" ? { ok: true } : {},
    ),
  },
});

describe("Shop offer bulk selection", () => {
  it("selects or deselects every currently filtered row", () => {
    const deps = createDeps();
    deps.offerSelection.push("offer:outside");
    installWorkspaceGroup7(deps);

    deps.toggleAllOfferItems();
    expect(deps.offerSelection).toEqual([
      "offer:outside",
      "offer:1",
      "offer:2",
    ]);

    deps.allFilteredOfferSelected.value = true;
    deps.toggleAllOfferItems();
    expect(deps.offerSelection).toEqual(["offer:outside"]);
  });

  it("moves every instance from selected groups into the owner trash", async () => {
    const deps = createDeps();
    deps.offerSelection.push("offer:1");
    installWorkspaceGroup7(deps);

    await deps.moveOfferSelectionToTrash();

    expect(deps.store.dispatch).toHaveBeenNthCalledWith(
      1,
      "shop/moveContainerItems",
      {
        moves: [
          {
            fromContainerId: 5,
            toContainerId: 9,
            instanceId: 10,
            quantity: 1,
          },
          {
            fromContainerId: 5,
            toContainerId: 9,
            instanceId: 11,
            quantity: 1,
          },
        ],
        ownerCode: "BG1",
      },
    );
    expect(deps.store.dispatch).toHaveBeenNthCalledWith(
      2,
      "shop/loadTradingData",
      {
        campaignId: 1,
        ownerCode: "BG1",
        forceReload: true,
      },
    );
    expect(deps.offerSelection).toEqual([]);
    expect(deps.offerSelectionBusy.value).toBe(false);
  });
});
