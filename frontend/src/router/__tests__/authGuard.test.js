import { createMemoryHistory, createRouter } from "vue-router";
import { describe, expect, it, vi } from "vitest";
import { createAuthGuard, safeRedirectTarget } from "@/router/authGuard";

const View = { template: "<div />" };
const buildRouter = () =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/", name: "home", component: View },
      {
        path: "/campaigns/:campaignId/scenes",
        name: "scene-workspace",
        component: View,
        meta: { requiresAuth: true },
      },
      {
        path: "/admin",
        name: "admin",
        component: View,
        meta: { requiresAuth: true, requiresAdmin: true },
      },
      {
        path: "/campaigns/:campaignId/manage",
        name: "campaign-manage",
        component: View,
        meta: { requiresAuth: true, requiresGm: true },
      },
      { path: "/403", name: "forbidden", component: View },
      { path: "/:pathMatch(.*)*", name: "not-found", component: View },
    ],
  });

describe("authentication route guard", () => {
  it("redirects an anonymous user and retains the intended internal path", async () => {
    const router = buildRouter();
    router.beforeEach(createAuthGuard({ isAuthenticated: () => false }));
    await router.push("/campaigns/9/scenes?tool=walls");

    expect(router.currentRoute.value).toMatchObject({
      name: "home",
      query: { redirect: "/campaigns/9/scenes?tool=walls" },
    });
  });

  it("allows administrators and rejects a known non-admin role", async () => {
    const adminRouter = buildRouter();
    adminRouter.beforeEach(
      createAuthGuard({
        isAuthenticated: () => true,
        read: () => ({ user: { role: "admin" } }),
      }),
    );
    await adminRouter.push("/admin");
    expect(adminRouter.currentRoute.value.name).toBe("admin");

    const playerRouter = buildRouter();
    playerRouter.beforeEach(
      createAuthGuard({
        isAuthenticated: () => true,
        read: () => ({ user: { role: "user" } }),
      }),
    );
    await playerRouter.push("/admin");
    expect(playerRouter.currentRoute.value.name).toBe("forbidden");
  });

  it("fails closed for an administrator without a trusted role", async () => {
    const router = buildRouter();
    router.beforeEach(
      createAuthGuard({
        isAuthenticated: () => true,
        read: () => ({ user: null }),
      }),
    );
    await router.push("/admin");
    expect(router.currentRoute.value.name).toBe("forbidden");
  });

  it("never derives campaign management from the global role", async () => {
    const session = {
      isAuthenticated: () => true,
      read: () => ({ user: { role: "admin" } }),
    };
    const denied = buildRouter();
    denied.beforeEach(createAuthGuard(session));
    await denied.push("/campaigns/7/manage");
    expect(denied.currentRoute.value.name).toBe("forbidden");

    const allowed = buildRouter();
    allowed.beforeEach(
      createAuthGuard(session, async (to) => to.params.campaignId === "7"),
    );
    await allowed.push("/campaigns/7/manage");
    expect(allowed.currentRoute.value.name).toBe("campaign-manage");
  });

  it("treats a campaign authorization 401 as an expired session", async () => {
    const session = {
      clear: vi.fn(),
      isAuthenticated: () => true,
      read: () => ({ user: { role: "player" } }),
    };
    const error = Object.assign(new Error("unauthorized"), { status: 401 });
    const router = buildRouter();
    router.beforeEach(
      createAuthGuard(session, async () => Promise.reject(error)),
    );

    await router.push("/campaigns/7/manage");

    expect(session.clear).toHaveBeenCalledWith("unauthorized");
    expect(router.currentRoute.value).toMatchObject({
      name: "home",
      query: { redirect: "/campaigns/7/manage" },
    });
  });

  it("accepts only a matched local redirect", () => {
    const router = buildRouter();
    expect(safeRedirectTarget(router, "/campaigns/3/scenes")).toBe(
      "/campaigns/3/scenes",
    );
    expect(safeRedirectTarget(router, "https://evil.test")).toBeNull();
    expect(safeRedirectTarget(router, "//evil.test")).toBeNull();
    expect(safeRedirectTarget(router, "/missing")).toBeNull();
  });
});
