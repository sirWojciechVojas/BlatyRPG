import { describe, expect, it, vi } from "vitest";
import { createHandoutApiClient } from "../handoutApiClient";

describe("handoutApiClient", () => {
  it("uses scoped campaign paths and only sends optimistic revisions", async () => {
    const request = vi.fn().mockResolvedValue({ handout: { id: 8 } });
    const client = createHandoutApiClient({ client: { request } });

    await client.updateCampaignHandout(7, 8, {
      title: "Clue",
      revision: 3,
    });
    await client.shareCampaignHandout(7, 8, {
      audienceMode: "selected_active_members",
      recipientIds: [2],
      revision: 4,
    });

    expect(request).toHaveBeenNthCalledWith(1, "/campaigns/7/handouts/8", {
      method: "PATCH",
      body: { title: "Clue", revision: 3 },
    });
    expect(request).toHaveBeenNthCalledWith(
      2,
      "/campaigns/7/handouts/8/share",
      {
        method: "POST",
        body: {
          audienceMode: "selected_active_members",
          recipientIds: [2],
          revision: 4,
        },
      },
    );
  });

  it("encodes list filters without putting them in a request body", async () => {
    const request = vi.fn().mockResolvedValue({ items: [] });
    const client = createHandoutApiClient({ client: { request } });

    await client.listCampaign(7, { q: "fog & fire", tag: "secret" });

    expect(request).toHaveBeenCalledWith(
      "/campaigns/7/handouts?q=fog+%26+fire&tag=secret",
      {},
    );
  });
});
