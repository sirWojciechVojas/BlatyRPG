import { describe, expect, it, vi } from "vitest";
import { createCampaignApiClient } from "@/lib/campaign/campaignApiClient";

describe("campaignApiClient", () => {
  it("normalizes legacy list fields and shop capability", async () => {
    const request = vi.fn().mockResolvedValue({
      items: [
        {
          id: "5",
          name: "Middenheim",
          system_type: "wfrp2ed",
          is_active: 1,
          access_role: "gm",
          capabilities: { canManage: true, can_view_hidden: true },
        },
      ],
      capabilities: { can_create: true },
    });

    await expect(createCampaignApiClient({ request }).list()).resolves.toEqual({
      campaigns: [
        expect.objectContaining({
          id: 5,
          systemType: "wfrp2ed",
          membershipRole: "gm",
          capabilities: expect.objectContaining({
            canManage: true,
            canViewHidden: true,
            canOpenShop: true,
          }),
        }),
      ],
      capabilities: { canCreate: true },
    });
  });

  it("posts the documented create shape and unwraps data.campaign", async () => {
    const request = vi.fn().mockResolvedValue({
      data: { campaign: { id: 8, name: "  Vault  ", systemType: "coc7e" } },
    });
    const client = createCampaignApiClient({ request });

    await expect(
      client.create({
        name: "  Vault  ",
        description: "  Below  ",
        systemType: "coc7e",
      }),
    ).resolves.toMatchObject({ id: 8, name: "  Vault  ", systemType: "coc7e" });
    expect(request).toHaveBeenCalledWith("/campaigns", {
      method: "POST",
      body: { name: "Vault", description: "Below", systemType: "coc7e" },
    });
  });

  it("posts managed RPG catalog IDs and normalizes them", async () => {
    const request = vi.fn().mockResolvedValue({
      campaign: {
        id: 9,
        name: "Reikland",
        system_id: "2",
        universe_id: "5",
      },
    });
    const client = createCampaignApiClient({ request });

    await expect(
      client.create({
        name: "Reikland",
        description: "Weekly",
        systemId: 2,
        universeId: 5,
      }),
    ).resolves.toMatchObject({ systemId: 2, universeId: 5 });
    expect(request).toHaveBeenCalledWith("/campaigns", {
      method: "POST",
      body: {
        name: "Reikland",
        description: "Weekly",
        systemId: 2,
        universeId: 5,
      },
    });
  });
});
