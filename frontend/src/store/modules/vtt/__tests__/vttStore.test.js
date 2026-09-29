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
    duplicate: vi.fn().mockResolvedValue({
      scene: scene({ id: 2, name: "Old road — copy", sortOrder: 1 }),
      capabilities: { canManage: true, canViewHidden: true },
    }),
    update: vi.fn(),
    remove: vi.fn().mockResolvedValue({ activeSceneId: null }),
    activate: vi.fn(),
  };
};

const setup = (api, realtime = null) => {
  const modules = { vtt: createVttModule(api) };
  if (realtime) modules.realtime = realtime;
  const store = createStore({ modules });
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

  it("tracks every scene data stage before the view preloads its media", async () => {
    const store = setup(apiMock(false));

    await store.dispatch("vtt/initialize", { showLoader: true });

    const steps = Object.fromEntries(
      store.state.vtt.sceneLoading.steps.map((step) => [step.id, step.status]),
    );
    expect(store.state.vtt.sceneLoading.active).toBe(true);
    expect(steps).toMatchObject({
      catalog: "ready",
      scene: "ready",
      tokens: "ready",
      tiles: "ready",
      walls: "ready",
      lights: "ready",
      fog: "ready",
      regions: "ready",
      combat: "ready",
      movement: "ready",
      assets: "pending",
      render: "pending",
    });
  });

  it("does not restart the entry loader while changing scenes", async () => {
    const store = setup(apiMock(false));

    await store.dispatch("vtt/initialize", { showLoader: true });
    store.commit("vtt/FINISH_SCENE_LOADING");
    await store.dispatch("vtt/selectScene", 1);

    expect(store.state.vtt.sceneLoading.active).toBe(false);
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

  it("duplicates and selects the full scene through the dedicated API", async () => {
    const api = apiMock(true);
    const store = setup(api);
    await store.dispatch("vtt/initialize");

    await store.dispatch("vtt/duplicateSelectedScene", "Old road — copy");

    expect(api.duplicate).toHaveBeenCalledWith(7, 1, "Old road — copy");
    expect(api.create).not.toHaveBeenCalled();
    expect(store.state.vtt.selectedSceneId).toBe(2);
  });

  it("publishes authoritative global illumination after a scene update", async () => {
    const api = apiMock(true);
    api.update.mockResolvedValue({
      scene: scene({ globalLightLevel: 0.2, revision: 2 }),
      capabilities: { canManage: true, canViewHidden: true },
    });
    const syncSceneLighting = vi.fn();
    const store = setup(api, {
      namespaced: true,
      actions: { syncSceneLighting },
    });
    await store.dispatch("vtt/initialize");
    await store.dispatch("vtt/updateSelectedScene", { globalLightLevel: 0.2 });

    expect(syncSceneLighting).toHaveBeenCalledWith(
      expect.any(Object),
      expect.objectContaining({ id: 1, globalLightLevel: 0.2, revision: 2 }),
    );
  });

  it("does not publish lighting when a full draft keeps it unchanged", async () => {
    const api = apiMock(true);
    const current = scene({ globalLightLevel: 0.8, gridSize: 100 });
    api.list.mockResolvedValue({
      items: [current],
      activeSceneId: 1,
      capabilities: { canManage: true, canViewHidden: true },
    });
    api.get.mockResolvedValue({
      scene: current,
      capabilities: { canManage: true, canViewHidden: true },
    });
    api.update.mockResolvedValue({
      scene: scene({ globalLightLevel: 0.8, gridSize: 120, revision: 2 }),
      capabilities: { canManage: true, canViewHidden: true },
    });
    const syncSceneLighting = vi.fn();
    const store = setup(api, {
      namespaced: true,
      actions: { syncSceneLighting },
    });
    await store.dispatch("vtt/initialize");

    await store.dispatch("vtt/updateScene", {
      sceneId: 1,
      changes: { gridSize: 120, globalLightLevel: 0.8 },
    });

    expect(syncSceneLighting).not.toHaveBeenCalled();
  });

  it("keeps an accepted update successful when realtime publication fails", async () => {
    const api = apiMock(true);
    const updated = scene({ globalLightLevel: 0.2, revision: 2 });
    api.update.mockResolvedValue({
      scene: updated,
      capabilities: { canManage: true, canViewHidden: true },
    });
    const syncSceneLighting = vi
      .fn()
      .mockRejectedValue(new Error("light_unavailable"));
    const store = setup(api, {
      namespaced: true,
      actions: { syncSceneLighting },
    });
    await store.dispatch("vtt/initialize");

    await expect(
      store.dispatch("vtt/updateScene", {
        sceneId: 1,
        changes: { globalLightLevel: 0.2 },
      }),
    ).resolves.toEqual(updated);
    expect(store.getters["vtt/selectedScene"]).toEqual(updated);
  });

  it("keeps an accepted update when another scene request starts meanwhile", async () => {
    const api = apiMock(true);
    const updated = scene({ gridSize: 120, revision: 2 });
    let resolveUpdate;
    api.update.mockReturnValue(
      new Promise((resolve) => {
        resolveUpdate = resolve;
      }),
    );
    const store = setup(api);
    await store.dispatch("vtt/initialize");

    const saving = store.dispatch("vtt/updateScene", {
      sceneId: 1,
      changes: { gridSize: 120 },
    });
    await store.dispatch("vtt/selectScene", 1);
    resolveUpdate({
      scene: updated,
      capabilities: { canManage: true, canViewHidden: true },
    });

    await expect(saving).resolves.toEqual(updated);
    expect(store.getters["vtt/selectedScene"]).toEqual(updated);
  });

  it("updates a scene by id without changing the current selection", async () => {
    const api = apiMock(true);
    api.list.mockResolvedValue({
      items: [scene(), scene({ id: 2, name: "Courtyard", revision: 4 })],
      activeSceneId: 1,
      capabilities: { canManage: true, canViewHidden: true },
    });
    api.update.mockResolvedValue({
      scene: scene({ id: 2, name: "Night courtyard", revision: 5 }),
      capabilities: { canManage: true, canViewHidden: true },
    });
    const store = setup(api);
    await store.dispatch("vtt/initialize");

    await store.dispatch("vtt/updateScene", {
      sceneId: 2,
      changes: { name: "Night courtyard" },
    });

    expect(api.update).toHaveBeenCalledWith(7, 2, {
      name: "Night courtyard",
      revision: 4,
    });
    expect(store.state.vtt.selectedSceneId).toBe(1);
    expect(store.state.vtt.scenes.find(({ id }) => id === 2).name).toBe(
      "Night courtyard",
    );
  });
});
