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
const light = {
  id: 6,
  sceneId: 4,
  x: 200,
  y: 300,
  brightRadius: 150,
  dimRadius: 350,
  intensity: 1,
  revision: 1,
};

describe("VTT light store", () => {
  it("loads, changes and removes lights in the selected scene", async () => {
    const lightApi = {
      list: vi.fn().mockResolvedValue({
        items: [light],
        capabilities: { canManage: true },
      }),
      update: vi
        .fn()
        .mockResolvedValue({ ...light, intensity: 0.5, revision: 2 }),
      remove: vi.fn().mockResolvedValue({ deleted: true }),
    };
    const store = createStore({
      modules: { vtt: createVttModule(sceneApi, null, null, lightApi) },
    });
    store.commit("vtt/SET_CAMPAIGN", 7);

    await store.dispatch("vtt/initialize");
    expect(store.getters["vtt/selectedSceneLights"]).toEqual([light]);
    await store.dispatch("vtt/updateLight", {
      light,
      changes: { intensity: 0.5 },
    });
    expect(lightApi.update).toHaveBeenCalledWith(7, 4, 6, {
      intensity: 0.5,
      revision: 1,
    });
    expect(store.getters["vtt/selectedSceneLights"][0].intensity).toBe(0.5);

    await store.dispatch("vtt/deleteLight", { ...light, revision: 2 });
    expect(store.getters["vtt/selectedSceneLights"]).toEqual([]);
  });
});
