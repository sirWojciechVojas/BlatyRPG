import { describe, expect, it, vi } from "vitest";
import { createAdminApiClient } from "../adminApiClient";

describe("adminApiClient", () => {
  it("normalizes the overview without exposing unrelated fields", async () => {
    const client = {
      request: vi.fn().mockResolvedValue({
        currentUserId: 2,
        users: [
          { id: "2", username: "admin", role: "ADMIN", campaignCount: "1" },
        ],
        campaigns: [{ id: "4", name: "World", isActive: 1, memberCount: "2" }],
        characters: [
          {
            id: "9",
            name: "Bruder Witz",
          },
        ],
        characterCampaigns: [
          { characterId: "9", campaignId: "4", campaignName: "World" },
        ],
        characterOwners: [
          {
            characterId: "9",
            campaignId: "4",
            userId: "2",
            username: "admin",
          },
        ],
        characterGameMasters: [
          {
            campaignId: "4",
            userId: "2",
            username: "admin",
            isCampaignOwner: true,
          },
        ],
        metrics: {
          users: "1",
          admins: "1",
          campaigns: "1",
          activeCampaigns: "1",
          memberships: "2",
          activeSessions: "1",
        },
        analytics: {
          accountRoles: [{ key: "admin", value: "1" }],
          growth: [{ label: "18.08", users: "1", campaigns: "0" }],
        },
      }),
    };
    const result = await createAdminApiClient(client).overview();

    expect(client.request).toHaveBeenCalledWith("/admin/overview", {});
    expect(result.users[0]).toMatchObject({
      id: 2,
      role: "admin",
      campaignCount: 1,
    });
    expect(result.campaigns[0]).toMatchObject({
      id: 4,
      memberCount: 2,
      status: "active",
    });
    expect(result.metrics).toMatchObject({
      activeCampaigns: 1,
      memberships: 2,
      activeSessions: 1,
    });
    expect(result.characters[0]).toMatchObject({
      id: 9,
    });
    expect(result.characterCampaigns[0]).toMatchObject({
      characterId: 9,
      campaignId: 4,
    });
    expect(result.characterOwners[0]).toMatchObject({ userId: 2 });
    expect(result.characterGameMasters[0]).toEqual({
      campaignId: 4,
      userId: 2,
      username: "admin",
      isCampaignOwner: true,
    });
    expect(result.analytics.accountRoles).toEqual([{ key: "admin", value: 1 }]);
    expect(result.analytics.growth[0]).toEqual({
      label: "18.08",
      users: 1,
      campaigns: 0,
    });
  });

  it("uses dedicated write endpoints", async () => {
    const client = {
      request: vi.fn().mockResolvedValue({ user: { id: 3, role: "user" } }),
    };
    const api = createAdminApiClient(client);
    await api.createUser({ username: "gm" });
    await api.changeUserRole(3, "user");
    await api.setCharacterCampaign(9, 4, true);
    await api.setCharacterOwner(9, 4, 2, false);

    expect(client.request).toHaveBeenNthCalledWith(1, "/admin/users", {
      method: "POST",
      body: { username: "gm" },
    });
    expect(client.request).toHaveBeenNthCalledWith(2, "/admin/users/3/role", {
      method: "PATCH",
      body: { role: "user" },
    });
    expect(client.request).toHaveBeenNthCalledWith(
      3,
      "/admin/characters/9/campaigns/4",
      { method: "PUT" },
    );
    expect(client.request).toHaveBeenNthCalledWith(
      4,
      "/admin/characters/9/campaigns/4/owners/2",
      { method: "DELETE" },
    );
  });
});
