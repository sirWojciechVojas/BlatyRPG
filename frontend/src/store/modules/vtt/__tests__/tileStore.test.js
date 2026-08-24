import { createStore } from "vuex";
import { describe, expect, it, vi } from "vitest";
import { createVttModule } from "@/store/modules/vtt";

const scene = { id: 4, name: "Crypt", sortOrder: 0, revision: 1 };
const sceneApi = {
  list: vi.fn().mockResolvedValue({
    items: [scene],
    activeSceneId: 4,
    capabilities: { canManage: true, canViewHidden: true },
  }),
  get: vi.fn().mockResolvedValue({
    scene,
    capabilities: { canManage: true, canViewHidden: true },
  }),
};
const tile = {
  id: 9,
  sceneId: 4,
  name: "Crate",
  assetUrl: "/assets/crate.webp",
  x: 200,
  y: 300,
  width: 100,
  height: 100,
  opacity: 1,
  revision: 1,
};

describe("VTT tile store", () => {
  it("loads, changes and removes tiles in the selected scene", async () => {
    const tileApi = {
      list: vi.fn().mockResolvedValue({
        items: [tile],
        capabilities: { canManage: true },
      }),
      update: vi.fn().mockResolvedValue({ ...tile, opacity: 0.5, revision: 2 }),
      remove: vi.fn().mockResolvedValue({ deleted: true }),
    };
    const store = createStore({
      modules: {
        vtt: createVttModule(sceneApi, null, null, null, tileApi),
      },
    });
    store.commit("vtt/SET_CAMPAIGN", 7);

    await store.dispatch("vtt/initialize");
    expect(store.getters["vtt/selectedSceneTiles"]).toEqual([tile]);
    await store.dispatch("vtt/updateTile", {
      tile,
      changes: { opacity: 0.5 },
    });
    expect(tileApi.update).toHaveBeenCalledWith(7, 4, 9, {
      opacity: 0.5,
      revision: 1,
    });

    await store.dispatch("vtt/deleteTile", { ...tile, revision: 2 });
    expect(store.getters["vtt/selectedSceneTiles"]).toEqual([]);
  });
});
