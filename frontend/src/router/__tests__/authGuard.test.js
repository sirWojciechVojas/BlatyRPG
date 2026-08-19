import { createMemoryHistory, createRouter } from "vue-router";
import { describe, expect, it, vi } from "vitest";
import { createAuthGuard, safeRedirectTarget } from "@/router/authGuard";

const View = { template: "<div />" };
const buildRouter = () =>
  createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: "/",
        name: "landing",
        component: View,
        meta: { redirectAuthenticated: true },
      },
      {
        path: "/login",
        name: "login",
        component: View,
        meta: { redirectAuthenticated: true },
      },
      {
        path: "/tables",
        name: "tables",
        component: View,
        meta: { requiresAuth: true },
      },
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

const authenticated = (role = "player") => ({
  isAuthenticated: () => true,
  read: () => ({ user: { role } }),
});

describe("authentication route guard", () => {
  it("redirects an anonymous user to login with the intended local path", async () => {
    const router = buildRouter();
    router.beforeEach(
      createAuthGuard({ read: () => null, isAuthenticated: () => false }),
    );
    await router.push("/campaigns/9/scenes?tool=walls");

    expect(router.currentRoute.value).toMatchObject({
      name: "login",
      query: { redirect: "/campaigns/9/scenes?tool=walls" },
    });
  });

  it.each([
    ["admin", "admin"],
    ["gm", "tables"],
    ["player", "tables"],
  ])(
    "sends an authenticated %s to its default view",
    async (role, routeName) => {
      const router = buildRouter();
      router.beforeEach(createAuthGuard(authenticated(role)));
      await router.push("/");

      expect(router.currentRoute.value.name).toBe(routeName);
    },
  );

  it("allows administrators and rejects a known non-admin role", async () => {
    const adminRouter = buildRouter();
    adminRouter.beforeEach(createAuthGuard(authenticated("admin")));
    await adminRouter.push("/admin");
    expect(adminRouter.currentRoute.value.name).toBe("admin");

    const playerRouter = buildRouter();
    playerRouter.beforeEach(createAuthGuard(authenticated("player")));
    await playerRouter.push("/admin");
    expect(playerRouter.currentRoute.value.name).toBe("forbidden");
  });

  it("never derives campaign management from the global role", async () => {
    const denied = buildRouter();
    denied.beforeEach(createAuthGuard(authenticated("admin")));
    await denied.push("/campaigns/7/manage");
    expect(denied.currentRoute.value.name).toBe("forbidden");

    const allowed = buildRouter();
    allowed.beforeEach(
      createAuthGuard(
        authenticated("admin"),
        async (to) => to.params.campaignId === "7",
      ),
    );
    await allowed.push("/campaigns/7/manage");
    expect(allowed.currentRoute.value.name).toBe("campaign-manage");
  });

  it("treats a campaign authorization 401 as an expired session", async () => {
    let active = true;
    const session = {
      read: () => (active ? { user: { role: "player" } } : null),
      isAuthenticated: () => active,
      clear: vi.fn(() => {
        active = false;
      }),
    };
    const error = Object.assign(new Error("unauthorized"), { status: 401 });
    const router = buildRouter();
    router.beforeEach(
      createAuthGuard(session, async () => Promise.reject(error)),
    );

    await router.push("/campaigns/7/manage");

    expect(session.clear).toHaveBeenCalledWith("unauthorized");
    expect(router.currentRoute.value).toMatchObject({
      name: "login",
      query: { redirect: "/campaigns/7/manage" },
    });
  });

  it("accepts only a matched, non-auth-entry local redirect", () => {
    const router = buildRouter();
    expect(safeRedirectTarget(router, "/campaigns/3/scenes")).toBe(
      "/campaigns/3/scenes",
    );
    expect(safeRedirectTarget(router, "/")).toBeNull();
    expect(safeRedirectTarget(router, "/login")).toBeNull();
    expect(safeRedirectTarget(router, "https://evil.test")).toBeNull();
    expect(safeRedirectTarget(router, "//evil.test")).toBeNull();
    expect(safeRedirectTarget(router, "/missing")).toBeNull();
  });
});
