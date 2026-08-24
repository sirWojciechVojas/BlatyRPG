import { createStore } from "vuex";
import { describe, expect, it, vi } from "vitest";
import { createVttModule } from "@/store/modules/vtt";

const scene = { id: 4, name: "Ruins", sortOrder: 0, revision: 1 };
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
const wall = {
  id: 8,
  sceneId: 4,
  type: "door",
  x1: 0,
  y1: 50,
  x2: 100,
  y2: 50,
  doorState: "closed",
  revision: 1,
};

describe("VTT wall store", () => {
  it("loads, changes and removes walls in the selected scene", async () => {
    const wallApi = {
      list: vi.fn().mockResolvedValue({
        items: [wall],
        capabilities: { canManage: true },
      }),
      update: vi.fn().mockResolvedValue({
        ...wall,
        doorState: "open",
        revision: 2,
      }),
      remove: vi.fn().mockResolvedValue({ deleted: true }),
    };
    const store = createStore({
      modules: { vtt: createVttModule(sceneApi, null, wallApi) },
    });
    store.commit("vtt/SET_CAMPAIGN", 7);

    await store.dispatch("vtt/initialize");
    expect(store.getters["vtt/selectedSceneWalls"]).toEqual([wall]);
    await store.dispatch("vtt/updateWall", {
      wall,
      changes: { doorState: "open" },
    });
    expect(wallApi.update).toHaveBeenCalledWith(7, 4, 8, {
      doorState: "open",
      revision: 1,
    });
    expect(store.getters["vtt/selectedSceneWalls"][0].doorState).toBe("open");

    await store.dispatch("vtt/deleteWall", { ...wall, revision: 2 });
    expect(store.getters["vtt/selectedSceneWalls"]).toEqual([]);
  });
});
