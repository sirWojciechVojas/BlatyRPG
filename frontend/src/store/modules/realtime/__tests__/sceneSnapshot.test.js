import { describe, expect, it, vi } from "vitest";
import { routeRealtimeSceneSnapshot } from "../sceneSnapshot";
import { vttMutations } from "@/store/modules/vtt/mutations";
import { createVttState } from "@/store/modules/vtt/state";

const snapshot = () => ({
  scenes: {
    items: [{ id: 4, campaignId: 7, name: "Ruins", revision: 3 }],
    activeSceneId: 4,
    capabilities: { canManage: false, canViewHidden: false },
  },
  scene: { id: 4, campaignId: 7, name: "Ruins", revision: 3 },
  capabilities: { canManage: false, canViewHidden: false },
  tokens: {
    items: [{ id: 9, sceneId: 4, name: "Ada", x: 10, y: 20, revision: 2 }],
    capabilities: { canCreate: false },
  },
  walls: { items: [], capabilities: { canManage: false } },
  lights: { items: [], capabilities: { canManage: false } },
  tiles: { items: [], capabilities: { canManage: false } },
  combat: { combat: null, capabilities: { canManage: false } },
  fog: {
    sceneId: 4,
    userId: 2,
    exploredRanges: [],
    forcedHiddenRanges: [],
    revision: 2,
  },
  movementRequests: { items: [], capabilities: { canResolve: false } },
});

describe("realtime scene snapshot", () => {
  it("replaces all selected-scene resources without entering a loading state", () => {
    const state = createVttState();
    state.campaignId = 7;
    state.selectedSceneId = 4;
    state.scenes = [{ id: 4, campaignId: 7, name: "Old ruins", revision: 2 }];
    state.tokensByScene = { 4: [{ id: 9, sceneId: 4, name: "Old Ada" }] };
    state.selectedTokenId = 9;
    state.selectedTokenIds = [9];
    state.tokenPhase = "ready";
    const commit = vi.fn((type, payload) => {
      vttMutations[String(type).replace("vtt/", "")](state, payload);
    });

    routeRealtimeSceneSnapshot(
      { rootState: { vtt: state }, commit },
      { type: "sync.snapshot", payload: { snapshot: snapshot() } },
    );

    expect(commit).toHaveBeenCalledWith(
      "vtt/RECEIVE_REALTIME_SCENE_SNAPSHOT",
      expect.any(Object),
      { root: true },
    );
    expect(state.scenes[0].name).toBe("Ruins");
    expect(state.tokensByScene[4][0].name).toBe("Ada");
    expect(state.selectedTokenIds).toEqual([9]);
    expect(state.tokenPhase).toBe("ready");
    expect(state.phase).toBe("idle");
  });

  it("ignores a malformed snapshot before it reaches the VTT store", () => {
    const commit = vi.fn();
    routeRealtimeSceneSnapshot(
      { rootState: { vtt: createVttState() }, commit },
      { type: "sync.snapshot", payload: { snapshot: { scene: {} } } },
    );
    expect(commit).not.toHaveBeenCalled();
  });
});
