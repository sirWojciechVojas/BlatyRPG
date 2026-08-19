import { createStore } from "vuex";
import { describe, expect, it, vi } from "vitest";
import { createCampaignContextModule } from "@/store/modules/campaignContext";

const campaign = (id, canManage = false) => ({
  id,
  name: `Campaign ${id}`,
  capabilities: {
    canAccess: true,
    canManage,
    canViewHidden: canManage,
    canOpenShop: canManage,
  },
});

const apiMock = (canManage = false) => ({
  enter: vi.fn((id) => Promise.resolve(campaign(id, canManage))),
  listMembers: vi.fn().mockResolvedValue({
    members: [{ id: 1, userId: 4, username: "Ada", role: "player" }],
  }),
  listCharacters: vi.fn().mockResolvedValue({
    characters: [{ id: 8, name: "Elsa", capabilities: { canEdit: true } }],
  }),
  listInvitations: vi.fn().mockResolvedValue([]),
  changeMemberRole: vi.fn((_campaignId, userId, role) =>
    Promise.resolve({ id: 1, userId, username: "Ada", role }),
  ),
});

const setup = (api) =>
  createStore({
    modules: { campaignContext: createCampaignContextModule(api) },
  });

describe("campaignContext", () => {
  it("loads only the server-authorized player subset", async () => {
    const api = apiMock(false);
    const store = setup(api);

    await store.dispatch("campaignContext/selectCampaign", 7);

    expect(api.listMembers).toHaveBeenCalledWith(7);
    expect(api.listCharacters).toHaveBeenCalledWith(7);
    expect(api.listInvitations).not.toHaveBeenCalled();
    expect(store.state.campaignContext.characters).toHaveLength(1);
    expect(store.getters["campaignContext/canManage"]).toBe(false);
  });

  it("ignores an old campaign response after a route switch", async () => {
    let resolveFirst;
    const api = apiMock(false);
    api.enter.mockImplementation((id) =>
      id === 1
        ? new Promise((resolve) => {
            resolveFirst = resolve;
          })
        : Promise.resolve(campaign(id)),
    );
    const store = setup(api);

    const oldRequest = store.dispatch("campaignContext/selectCampaign", 1);
    await store.dispatch("campaignContext/selectCampaign", 2);
    resolveFirst(campaign(1));
    await oldRequest;

    expect(store.state.campaignContext.campaignId).toBe(2);
    expect(store.state.campaignContext.currentCampaign.id).toBe(2);
  });

  it("always scopes writes to currentCampaign instead of payload campaignId", async () => {
    const api = apiMock(true);
    const store = setup(api);
    await store.dispatch("campaignContext/selectCampaign", 7);

    await store.dispatch("campaignContext/changeMemberRole", {
      campaignId: 999,
      userId: 4,
      role: "observer",
    });

    expect(api.changeMemberRole).toHaveBeenCalledWith(7, 4, "observer");
  });

  it("blocks manager operations before the API for a player", async () => {
    const api = apiMock(false);
    api.invite = vi.fn();
    const store = setup(api);
    await store.dispatch("campaignContext/selectCampaign", 7);

    await expect(
      store.dispatch("campaignContext/invite", {
        identifier: "user@example.test",
      }),
    ).rejects.toMatchObject({ status: 403 });
    expect(api.invite).not.toHaveBeenCalled();
  });
});
