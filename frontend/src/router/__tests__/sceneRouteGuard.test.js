import { createMemoryHistory, createRouter } from "vue-router";
import { describe, expect, it, vi } from "vitest";
import { ensureVttStoreModuleForRoute } from "@/store/modules/loadVttModule";

const EmptyView = { template: "<div />" };

describe("scene workspace route guard", () => {
  it("loads the VTT store before entering the campaign workspace", async () => {
    let registered = false;
    const store = {
      state: {},
      hasModule: vi.fn(() => registered),
      registerModule: vi.fn(() => {
        registered = true;
      }),
    };
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: "/", component: EmptyView },
        {
          path: "/campaigns/:campaignId/scenes",
          name: "scene-workspace",
          component: EmptyView,
          beforeEnter: () => ensureVttStoreModuleForRoute(store),
        },
      ],
    });

    await router.push("/");
    await router.push("/campaigns/1/scenes");

    expect(router.currentRoute.value.name).toBe("scene-workspace");
    expect(store.registerModule).toHaveBeenCalledWith(
      "vtt",
      expect.objectContaining({ namespaced: true }),
    );
  });
});
