import { createStore } from "vuex";
import { describe, expect, it, vi } from "vitest";
import { createVttModule } from "@/store/modules/vtt";

describe("VTT campaign isolation", () => {
  it("ignores a write response from the previously selected campaign", async () => {
    let resolveCreate;
    const api = {
      create: vi.fn(
        () =>
          new Promise((resolve) => {
            resolveCreate = resolve;
          }),
      ),
    };
    const store = createStore({ modules: { vtt: createVttModule(api) } });
    store.commit("vtt/SET_CAMPAIGN", 1);
    store.state.vtt.capabilities.canManage = true;

    const pending = store.dispatch("vtt/createScene", { name: "Old scene" });
    store.commit("vtt/SET_CAMPAIGN", 2);
    resolveCreate({
      scene: { id: 10, campaignId: 1, name: "Old scene", revision: 1 },
      capabilities: { canManage: true, canViewHidden: true },
    });
    await pending;

    expect(store.state.vtt.campaignId).toBe(2);
    expect(store.state.vtt.scenes).toEqual([]);
    expect(store.state.vtt.capabilities.canManage).toBe(false);
    expect(store.state.vtt.phase).toBe("idle");
  });
});
