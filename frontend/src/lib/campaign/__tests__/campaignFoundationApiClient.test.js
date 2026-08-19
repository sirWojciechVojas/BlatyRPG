import { describe, expect, it, vi } from "vitest";
import { createCampaignApiClient } from "@/lib/campaign/campaignApiClient";

describe("campaign foundation API", () => {
  it("uses campaign-scoped member and character routes", async () => {
    const request = vi
      .fn()
      .mockResolvedValueOnce({ items: [] })
      .mockResolvedValueOnce({ items: [] });
    const api = createCampaignApiClient({ request });

    await api.listMembers(3);
    await api.listCharacters(3);

    expect(request.mock.calls[0][0]).toBe("/campaigns/3/members");
    expect(request.mock.calls[1][0]).toBe("/campaigns/3/characters");
  });

  it("validates path values and sends the canonical character ACL body", async () => {
    const request = vi.fn().mockResolvedValue({
      permission: {
        id: 10,
        campaignId: 3,
        resourceType: "character",
        resourceId: 8,
        user: { id: 5 },
        accessLevel: "owner",
      },
    });
    const api = createCampaignApiClient({ request });

    await api.setResourcePermission(3, "character", 8, 5, "OWNER");

    expect(request).toHaveBeenCalledWith(
      "/campaigns/3/resources/character/8/permissions/5",
      { method: "PUT", body: { accessLevel: "owner" } },
    );
    await expect(
      api.setResourcePermission(3, "../users", 8, 5, "owner"),
    ).rejects.toThrow("invalid_resource_type");
  });

  it("binds a realtime ticket to the supplied client instance", async () => {
    const request = vi.fn().mockResolvedValue({ ticket: "signed" });
    await createCampaignApiClient({ request }).realtimeTicket(
      4,
      "rt_1234567890123456",
    );
    expect(request).toHaveBeenCalledWith("/campaigns/4/realtime-ticket", {
      method: "POST",
      body: { clientInstanceId: "rt_1234567890123456" },
    });
  });

  it("lists and responds to invitations as the invitee", async () => {
    const request = vi
      .fn()
      .mockResolvedValueOnce({
        invitations: [{ id: 12, campaignId: 4, campaignName: "Reikland" }],
      })
      .mockResolvedValueOnce({ status: "accepted" });
    const api = createCampaignApiClient({ request });

    await expect(api.listMyInvitations()).resolves.toMatchObject([
      { id: 12, campaignId: 4, campaignName: "Reikland" },
    ]);
    await api.respondToInvitation(12, "accept");

    expect(request.mock.calls).toEqual([
      ["/campaign-invitations", {}],
      ["/campaign-invitations/12/accept", { method: "POST" }],
    ]);
  });
});
