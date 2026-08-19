import { describe, expect, it, vi } from "vitest";
import {
  createCampaignAuthorization,
  createCampaignSessionGuard,
  ensureCampaignRouteSession,
  leaveCampaignSession,
} from "@/router/campaignSessionGuard";
import { ensureCampaignContextForRoute } from "@/store/modules/loadCampaignContextModule";

const route = (campaignId, meta = {}) => ({
  fullPath: campaignId ? `/campaigns/${campaignId}/scenes` : "/about",
  params: campaignId ? { campaignId: String(campaignId) } : {},
  matched: [{ meta }],
});

const setupStore = (campaignId = 1) => {
  const state = {
    campaignContext: {
      campaignId,
      currentCampaign: campaignId ? { id: campaignId } : null,
      capabilities: { canManage: false, canOpenShop: false },
    },
    realtime: { campaignId },
  };
  const dispatch = vi.fn(async (type, payload) => {
    if (type === "campaignContext/selectCampaign") {
      state.campaignContext.campaignId = Number(payload);
      state.campaignContext.currentCampaign = { id: Number(payload) };
    }
    if (type === "campaignContext/leaveCampaign") {
      state.campaignContext.campaignId = null;
    }
    if (type === "realtime/connect")
      state.realtime.campaignId = Number(payload);
    if (type === "realtime/disconnect") state.realtime.campaignId = null;
  });
  return {
    state,
    dispatch,
    hasModule: (name) => Boolean(state[name]),
  };
};

describe("campaignSessionGuard", () => {
  it("switches campaign context and realtime together", async () => {
    const store = setupStore(1);
    const result = await createCampaignSessionGuard(store)(route(2), route(1));

    expect(result).toBe(true);
    expect(store.dispatch.mock.calls).toEqual([
      ["campaignContext/selectCampaign", 2],
      ["realtime/connect", 2],
    ]);
  });

  it("keeps the session while navigating inside one campaign", async () => {
    const store = setupStore(7);
    const result = await createCampaignSessionGuard(store)(route(7), route(7));

    expect(result).toBe(true);
    expect(store.dispatch).not.toHaveBeenCalledWith("realtime/disconnect");
    expect(store.dispatch).not.toHaveBeenCalledWith(
      "campaignContext/leaveCampaign",
    );
  });

  it("does not connect a superseded campaign after a B to C race", async () => {
    const store = setupStore(1);
    let releaseSelection;
    store.dispatch.mockImplementation((type) => {
      if (type === "campaignContext/selectCampaign") {
        return new Promise((resolve) => {
          releaseSelection = resolve;
        });
      }
      return Promise.resolve();
    });

    const pending = ensureCampaignRouteSession(store, route(2));
    await Promise.resolve();
    store.state.campaignContext.campaignId = 3;
    store.state.campaignContext.currentCampaign = { id: 3 };
    releaseSelection();

    await expect(pending).resolves.toBeNull();
    expect(store.dispatch).not.toHaveBeenCalledWith("realtime/connect", 2);
  });

  it("retries backend entry after a rejected campaign selection", async () => {
    const store = setupStore(null);
    let attempt = 0;
    store.dispatch.mockImplementation(async (type, campaignId) => {
      if (type !== "campaignContext/selectCampaign") return;
      attempt += 1;
      store.state.campaignContext.campaignId = campaignId;
      if (attempt === 1) {
        store.state.campaignContext.phase = "error";
        store.state.campaignContext.unauthorized = true;
        store.state.campaignContext.error = { status: 403 };
        throw Object.assign(new Error("forbidden"), { status: 403 });
      }
      store.state.campaignContext.currentCampaign = { id: campaignId };
      store.state.campaignContext.phase = "ready";
      store.state.campaignContext.unauthorized = false;
      store.state.campaignContext.error = null;
    });
    const target = route(7);

    await expect(
      ensureCampaignContextForRoute(store, target),
    ).rejects.toMatchObject({ status: 403 });
    await expect(
      ensureCampaignContextForRoute(store, target),
    ).resolves.toBeUndefined();

    expect(store.dispatch).toHaveBeenCalledTimes(2);
    expect(store.state.campaignContext.currentCampaign).toEqual({ id: 7 });
  });

  it("cleans both stores when leaving campaign routes", async () => {
    const store = setupStore(7);
    await leaveCampaignSession(store);

    expect(store.dispatch).toHaveBeenCalledWith("realtime/disconnect");
    expect(store.dispatch).toHaveBeenCalledWith(
      "campaignContext/leaveCampaign",
    );
  });

  it("uses backend campaign capabilities for requiresGm", async () => {
    const store = setupStore(7);
    const gmRoute = route(7, { requiresAuth: true, requiresGm: true });
    const authorize = createCampaignAuthorization(store);

    expect(await authorize(gmRoute)).toBe(false);
    store.state.campaignContext.capabilities.canOpenShop = true;
    expect(await authorize(gmRoute)).toBe(true);
  });
});
