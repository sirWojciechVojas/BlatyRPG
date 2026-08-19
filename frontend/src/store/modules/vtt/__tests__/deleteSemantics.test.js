import { createStore } from "vuex";
import { describe, expect, it, vi } from "vitest";
import { createVttModule } from "@/store/modules/vtt";

const firstScene = { id: 1, name: "Old road", sortOrder: 0, revision: 1 };
const nextScene = { id: 2, name: "Crossroads", sortOrder: 1, revision: 1 };
const capabilities = { canManage: true, canViewHidden: true };

describe("VTT scene deletion", () => {
  it("replaces the active scene from the delete response", async () => {
    const api = {
      list: vi.fn().mockResolvedValue({
        items: [firstScene, nextScene],
        activeSceneId: 1,
        capabilities,
      }),
      get: vi.fn().mockImplementation((_campaignId, sceneId) =>
        Promise.resolve({
          scene: sceneId === 1 ? firstScene : nextScene,
          capabilities,
        }),
      ),
      remove: vi.fn().mockResolvedValue({ activeSceneId: 2 }),
    };
    const store = createStore({ modules: { vtt: createVttModule(api) } });
    store.commit("vtt/SET_CAMPAIGN", 7);
    await store.dispatch("vtt/initialize");

    await store.dispatch("vtt/deleteSelectedScene");

    expect(api.remove).toHaveBeenCalledWith(7, 1, 1);
    expect(store.state.vtt.activeSceneId).toBe(2);
    expect(store.state.vtt.selectedSceneId).toBe(2);
    expect(store.state.vtt.scenes.map(({ id }) => id)).toEqual([2]);
  });

  it("clears an active id immediately when its scene is removed", () => {
    const store = createStore({ modules: { vtt: createVttModule({}) } });
    store.state.vtt.scenes = [firstScene];
    store.state.vtt.activeSceneId = 1;
    store.state.vtt.selectedSceneId = 1;

    store.commit("vtt/REMOVE_SCENE", 1);

    expect(store.state.vtt.activeSceneId).toBeNull();
    expect(store.state.vtt.selectedSceneId).toBeNull();
  });
});
