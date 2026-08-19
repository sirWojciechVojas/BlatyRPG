import { createStore } from "vuex";
import { describe, expect, it, vi } from "vitest";
import { createVttModule } from "@/store/modules/vtt";

const scene = (overrides = {}) => ({
  id: 1,
  name: "Old road",
  sortOrder: 0,
  revision: 1,
  ...overrides,
});

const apiMock = (canManage) => {
  const first = scene();
  return {
    list: vi.fn().mockResolvedValue({
      items: [first],
      activeSceneId: 1,
      capabilities: { canManage, canViewHidden: canManage },
    }),
    get: vi.fn().mockResolvedValue({
      scene: first,
      capabilities: { canManage, canViewHidden: canManage },
    }),
    create: vi.fn().mockResolvedValue({
      scene: scene({ id: 2, name: "New scene" }),
      capabilities: { canManage: true, canViewHidden: true },
    }),
    update: vi.fn(),
    remove: vi.fn().mockResolvedValue({ activeSceneId: null }),
    activate: vi.fn(),
  };
};

const setup = (api) => {
  const store = createStore({ modules: { vtt: createVttModule(api) } });
  store.commit("vtt/SET_CAMPAIGN", 7);
  return store;
};

describe("VTT scene store", () => {
  it("loads a collection and then the selected scene snapshot", async () => {
    const api = apiMock(false);
    const store = setup(api);
    await store.dispatch("vtt/initialize");

    expect(api.list).toHaveBeenCalledWith(7);
    expect(api.get).toHaveBeenCalledWith(7, 1);
    expect(store.getters["vtt/selectedScene"].name).toBe("Old road");
    expect(store.state.vtt.phase).toBe("ready");
  });

  it("blocks player writes before they reach the API", async () => {
    const api = apiMock(false);
    const store = setup(api);
    await store.dispatch("vtt/initialize");

    await expect(
      store.dispatch("vtt/createScene", { name: "Forbidden scene" }),
    ).rejects.toMatchObject({ status: 403 });
    expect(api.create).not.toHaveBeenCalled();
  });

  it("creates and selects a scene for a manager", async () => {
    const api = apiMock(true);
    const store = setup(api);
    await store.dispatch("vtt/initialize");
    await store.dispatch("vtt/createScene", { name: "New scene" });

    expect(api.create).toHaveBeenCalledWith(7, { name: "New scene" });
    expect(store.state.vtt.selectedSceneId).toBe(2);
    expect(store.getters["vtt/selectedScene"].name).toBe("New scene");
  });
});
