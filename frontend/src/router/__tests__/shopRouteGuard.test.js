import { createMemoryHistory, createRouter } from "vue-router";
import { describe, expect, it, vi } from "vitest";
import { ensureShopStoreModuleForRoute } from "@/store/modules/loadShopModule";

const EmptyView = { template: "<div />" };

describe("shop GM route guard", () => {
  it("continues navigation after the shop store is available", async () => {
    const store = {
      state: { shop: { ready: true } },
      hasModule: vi.fn(() => true),
      registerModule: vi.fn(),
    };
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: "/", component: EmptyView },
        {
          path: "/campaigns/:campaignId/shop",
          name: "shop-gm",
          component: EmptyView,
          beforeEnter: () => ensureShopStoreModuleForRoute(store),
        },
      ],
    });

    await router.push("/");
    await router.push("/campaigns/1/shop");

    expect(router.currentRoute.value.name).toBe("shop-gm");
    expect(store.hasModule).toHaveBeenCalledWith("shop");
    expect(store.registerModule).not.toHaveBeenCalled();
  });
});
