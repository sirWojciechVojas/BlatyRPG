import { createStore } from "vuex";
import { describe, expect, it, vi } from "vitest";
import { createVttModule } from "@/store/modules/vtt";

const sceneApi = {
  list: vi.fn().mockResolvedValue({
    items: [{ id: 4, name: "Ruins", sortOrder: 0, revision: 1 }],
    activeSceneId: 4,
    capabilities: { canManage: true, canViewHidden: true },
  }),
  get: vi.fn().mockResolvedValue({
    scene: { id: 4, name: "Ruins", sortOrder: 0, revision: 1 },
    capabilities: { canManage: true, canViewHidden: true },
  }),
};

const token = {
  id: 9,
  sceneId: 4,
  name: "Guard",
  x: 10,
  y: 20,
  revision: 1,
};

describe("VTT token store", () => {
  it("loads, moves and removes tokens within the selected scene", async () => {
    const tokenApi = {
      list: vi.fn().mockResolvedValue({
        items: [token],
        capabilities: { canCreate: true },
      }),
      update: vi.fn().mockResolvedValue({ ...token, x: 30, revision: 2 }),
      remove: vi.fn().mockResolvedValue({ deleted: true }),
    };
    const store = createStore({
      modules: { vtt: createVttModule(sceneApi, tokenApi) },
    });
    store.commit("vtt/SET_CAMPAIGN", 7);

    await store.dispatch("vtt/initialize");
    expect(store.getters["vtt/selectedSceneTokens"]).toEqual([token]);
    store.commit("vtt/SELECT_TOKEN", 9);
    store.commit("vtt/UPSERT_TOKEN", { ...token, id: 10 });
    store.commit("vtt/SELECT_TOKEN", { tokenId: 10, additive: true });
    expect(store.state.vtt.selectedTokenIds).toEqual([9, 10]);
    expect(store.getters["vtt/selectedTokens"]).toHaveLength(2);
    store.commit("vtt/SELECT_TOKENS", { tokenIds: [9], additive: false });
    expect(store.state.vtt.selectedTokenIds).toEqual([9]);
    store.commit("vtt/SELECT_TOKENS", { tokenIds: [10], additive: true });
    expect(store.state.vtt.selectedTokenIds).toEqual([9, 10]);
    store.commit("vtt/SELECT_TOKEN", { tokenId: null });
    expect(store.state.vtt.selectedTokenId).toBeNull();
    expect(store.state.vtt.selectedTokenIds).toEqual([]);
    store.commit("vtt/SELECT_TOKEN", 9);
    store.commit("vtt/SELECT_TOKEN", { tokenId: 10, additive: true });
    store.commit("vtt/TOGGLE_TOKEN_TARGET", 9);
    store.commit("vtt/TOGGLE_TOKEN_TARGET", 10);
    expect(store.state.vtt.targetedTokenIds).toEqual([9, 10]);
    expect(store.getters["vtt/targetedTokens"]).toHaveLength(2);
    await store.dispatch("vtt/updateToken", { token, changes: { x: 30 } });
    expect(tokenApi.update).toHaveBeenCalledWith(7, 4, 9, {
      x: 30,
      revision: 1,
    });
    expect(store.getters["vtt/selectedSceneTokens"][0].x).toBe(30);
    await store.dispatch("vtt/deleteToken", { ...token, revision: 2 });
    expect(store.getters["vtt/selectedSceneTokens"]).toEqual([
      { ...token, id: 10 },
    ]);
    expect(store.state.vtt.selectedTokenIds).toEqual([10]);
    expect(store.state.vtt.targetedTokenIds).toEqual([10]);
  });
});
