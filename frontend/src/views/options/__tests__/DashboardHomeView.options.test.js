import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("@/components/dashboard/CampaignCard.vue", () => ({ default: {} }));
vi.mock("@/components/dashboard/CampaignCreateForm.vue", () => ({
  default: {},
}));
vi.mock("@/components/dashboard/DashboardLoginPanel.vue", () => ({
  default: {},
}));

import options from "@/views/options/DashboardHomeView.options";
import { authApiClient, createAuthApiClient } from "@/lib/auth/authApiClient";
import { authSession } from "@/lib/auth/authSession";

const loginPayload = {
  status: "success",
  access_token: "header.payload.signature",
  token_type: "Bearer",
  expires_in: 3600,
  user: {
    id: 2,
    username: "admin",
    login: "admin",
    email: "admin@blatyrpg.local",
    role: "admin",
    avatar_url: null,
  },
};

describe("DashboardHomeView login", () => {
  beforeEach(() => {
    window.localStorage.clear();
    authSession.clear("test_setup");
  });

  afterEach(() => {
    vi.restoreAllMocks();
    authSession.clear("test_cleanup");
  });

  it("accepts the real login payload and continues to the dashboard", async () => {
    const client = createAuthApiClient({
      request: vi.fn().mockResolvedValue(loginPayload),
    });
    vi.spyOn(authApiClient, "login").mockImplementation((credentials) =>
      client.login(credentials),
    );

    const context = {
      ...options.data(),
      $route: { name: "home", query: {} },
      $router: { replace: vi.fn() },
      $t: (key) => key,
      loadDashboard: vi.fn().mockResolvedValue(),
    };
    context.errorMessage = options.methods.errorMessage.bind(context);
    const unsubscribe = authSession.subscribe(
      (session) => {
        context.session = session;
      },
      { immediate: false },
    );

    await options.methods.login.call(context, {
      login: "admin@blatyrpg.local",
      password: "valid-password",
    });
    unsubscribe();

    expect(context.loginError).toBe("");
    expect(context.session).toMatchObject({
      token: "header.payload.signature",
      user: { id: 2, username: "admin", role: "admin" },
    });
    expect(context.loadDashboard).toHaveBeenCalledOnce();
    expect(context.$router.replace).not.toHaveBeenCalled();
    expect(window.localStorage.getItem("access_token")).toBe(
      "header.payload.signature",
    );
  });

  it("keeps a valid session when post-login navigation fails", async () => {
    const client = createAuthApiClient({
      request: vi.fn().mockResolvedValue(loginPayload),
    });
    vi.spyOn(authApiClient, "login").mockImplementation((credentials) =>
      client.login(credentials),
    );
    const navigationError = new Error("chunk_load_failed");
    const context = {
      ...options.data(),
      $route: {
        name: "home",
        query: { redirect: "/campaigns/1/scenes" },
      },
      $router: {
        resolve: vi.fn().mockReturnValue({
          matched: [{}],
          name: "scene-workspace",
          fullPath: "/campaigns/1/scenes",
        }),
        replace: vi.fn().mockRejectedValue(navigationError),
      },
      $t: (key) => key,
      loadDashboard: vi.fn().mockResolvedValue(),
    };
    context.errorMessage = options.methods.errorMessage.bind(context);

    await options.methods.login.call(context, {
      login: "admin@blatyrpg.local",
      password: "valid-password",
    });

    expect(context.loginError).toBe("");
    expect(context.dashboardError).toBe("dashboard.errors.navigation");
    expect(context.session).toMatchObject({
      token: "header.payload.signature",
      user: { id: 2 },
    });
    expect(context.loadDashboard).toHaveBeenCalledOnce();
    expect(authSession.read()).toMatchObject({
      token: "header.payload.signature",
    });
  });
});
