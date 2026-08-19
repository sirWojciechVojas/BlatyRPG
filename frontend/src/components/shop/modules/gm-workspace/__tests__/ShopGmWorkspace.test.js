import { createApp, h, nextTick } from "vue";
import { createMemoryHistory, createRouter } from "vue-router";
import { createStore } from "vuex";
import { afterEach, describe, expect, it } from "vitest";
import i18n from "@/i18n";
import { useShopGmWorkspace } from "@/components/shop/modules/gm-workspace/composables/useShopGmWorkspace";
import shop from "@/store/modules/shop";

const EmptyRoute = { template: "<div />" };
let mountedApp;

afterEach(() => {
  mountedApp?.unmount();
  mountedApp = null;
  document.body.innerHTML = "";
});

describe("ShopGmWorkspace", () => {
  it("mounts the split workspace without missing context dependencies", async () => {
    let workspace;
    const WorkspaceHarness = {
      setup() {
        workspace = useShopGmWorkspace();
        return () => h("div", { class: "workspace-harness" });
      },
    };
    const store = createStore({ modules: { shop } });
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        {
          path: "/campaigns/:campaignId/shop",
          component: WorkspaceHarness,
        },
        { path: "/", name: "home", component: EmptyRoute },
        { path: "/403", name: "forbidden", component: EmptyRoute },
      ],
    });
    await router.push("/campaigns/1/shop");
    await router.isReady();

    const host = document.createElement("div");
    document.body.append(host);
    mountedApp = createApp({ template: "<router-view />" });
    mountedApp.use(store).use(router).use(i18n).mount(host);
    await nextTick();

    expect(host.querySelector(".workspace-harness")).not.toBeNull();
    expect(workspace.emptyTemplate).toBeTypeOf("function");
    expect(workspace.filteredOffer).toBeDefined();
    expect(workspace.initializeWorkspace).toBeTypeOf("function");
  });
});
