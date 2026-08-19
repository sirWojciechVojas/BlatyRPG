import { describe, expect, it, vi } from "vitest";
import { installAuthLifecycle } from "@/lib/auth/authLifecycle";

describe("authLifecycle", () => {
  it("cleans campaign state and redirects when a session expires", async () => {
    let listener;
    const session = {
      subscribe: vi.fn((callback) => {
        listener = callback;
        callback({ token: "valid" }, "initial");
        return vi.fn();
      }),
    };
    const store = {
      state: { realtime: {}, campaignContext: {} },
      hasModule: () => true,
      dispatch: vi.fn().mockResolvedValue(undefined),
    };
    const router = {
      currentRoute: {
        value: {
          fullPath: "/campaigns/7/scenes",
          matched: [{ meta: { requiresAuth: true } }],
        },
      },
      replace: vi.fn().mockResolvedValue(undefined),
    };
    const onSession = vi.fn();
    installAuthLifecycle({ session, store, router, onSession });

    await listener(null, "expired");

    expect(store.dispatch).toHaveBeenCalledWith("realtime/disconnect");
    expect(store.dispatch).toHaveBeenCalledWith(
      "campaignContext/leaveCampaign",
    );
    expect(router.replace).toHaveBeenCalledWith({
      name: "home",
      query: { redirect: "/campaigns/7/scenes" },
    });
    expect(onSession).toHaveBeenLastCalledWith(null, "expired");
  });
});
