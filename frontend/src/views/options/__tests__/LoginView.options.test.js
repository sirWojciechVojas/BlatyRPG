import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("@/components/dashboard/DashboardLoginPanel.vue", () => ({
  default: {},
}));
import { authApiClient } from "@/lib/auth/authApiClient";
import { authSession } from "@/lib/auth/authSession";
import options from "@/views/options/LoginView.options";

const result = (role = "player") => ({
  token: "header.payload.signature",
  expiresIn: 3600,
  user: {
    id: 2,
    username: "tester",
    email: "tester@blatyrpg.local",
    role,
  },
});

const context = (redirect) => {
  const instance = {
    ...options.data(),
    $route: { query: { redirect } },
    $router: {
      resolve: vi.fn((path) => ({
        matched: [{}],
        name: "scene-workspace",
        fullPath: path,
      })),
      replace: vi.fn().mockResolvedValue(undefined),
    },
    $t: (key) => key,
  };
  instance.message = options.methods.message.bind(instance);
  return instance;
};

describe("LoginView", () => {
  beforeEach(() => {
    window.localStorage.clear();
    authSession.clear("test_setup");
  });

  afterEach(() => {
    vi.restoreAllMocks();
    authSession.clear("test_cleanup");
  });

  it.each([
    ["admin", { name: "admin" }],
    ["player", { name: "tables" }],
  ])("routes %s to its default view", async (role, target) => {
    vi.spyOn(authApiClient, "login").mockResolvedValue(result(role));
    const instance = context();

    await options.methods.login.call(instance, {
      login: "tester",
      password: "valid-password",
    });

    expect(instance.$router.replace).toHaveBeenCalledWith(target);
    expect(authSession.read()).toMatchObject({ user: { role } });
  });

  it("restores a safe route before the role default", async () => {
    vi.spyOn(authApiClient, "login").mockResolvedValue(result("admin"));
    const instance = context("/campaigns/7/scenes");

    await options.methods.login.call(instance, {
      login: "tester",
      password: "valid-password",
    });

    expect(instance.$router.replace).toHaveBeenCalledWith(
      "/campaigns/7/scenes",
    );
  });

  it("keeps the saved session when navigation fails", async () => {
    vi.spyOn(authApiClient, "login").mockResolvedValue(result());
    const instance = context();
    instance.$router.replace.mockRejectedValue(new Error("chunk_load_failed"));

    await options.methods.login.call(instance, {
      login: "tester",
      password: "valid-password",
    });

    expect(instance.error).toBe("auth.errors.navigation");
    expect(authSession.read()).toMatchObject({ user: { id: 2 } });
  });

  it("clears the session and presents a login error on rejection", async () => {
    vi.spyOn(authApiClient, "login").mockRejectedValue(
      Object.assign(new Error("unauthorized"), { status: 401 }),
    );
    const instance = context();

    await options.methods.login.call(instance, {
      login: "tester",
      password: "invalid-password",
    });

    expect(instance.error).toBe("auth.errors.invalidCredentials");
    expect(authSession.read()).toBeNull();
  });

  it("ignores a second submit while authentication is in progress", async () => {
    let resolveRequest;
    const pendingRequest = new Promise((resolveRequestPromise) => {
      resolveRequest = resolveRequestPromise;
    });
    const loginRequest = vi
      .spyOn(authApiClient, "login")
      .mockReturnValue(pendingRequest);
    const instance = context();
    const credentials = {
      login: "tester",
      password: "valid-password",
    };

    const firstAttempt = options.methods.login.call(instance, credentials);
    await Promise.resolve();
    await options.methods.login.call(instance, credentials);

    expect(loginRequest).toHaveBeenCalledTimes(1);
    expect(instance.busy).toBe(true);

    resolveRequest(result());
    await firstAttempt;

    expect(instance.busy).toBe(false);
  });
});
