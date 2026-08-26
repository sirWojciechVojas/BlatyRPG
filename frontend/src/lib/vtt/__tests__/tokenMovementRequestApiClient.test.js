import { describe, expect, it, vi } from "vitest";
import { createTokenMovementRequestApiClient } from "@/lib/vtt/tokenMovementRequestApiClient";

describe("tokenMovementRequestApiClient", () => {
  it("loads pending requests without polling or client identity fields", async () => {
    const request = vi.fn().mockResolvedValue({
      items: [
        {
          id: "31",
          campaignId: 7,
          sceneId: 4,
          tokenId: 9,
          requestedByUserId: 2,
          tokenName: "Guard",
          requesterName: "Player",
          cost: "8.000",
          spent: "2.000",
          range: "6.000",
          status: "pending",
        },
      ],
      capabilities: { canResolve: true },
    });
    const client = createTokenMovementRequestApiClient({ request });

    const result = await client.list(7);

    expect(request).toHaveBeenCalledWith(
      "/campaigns/7/token-movement-requests",
      {},
    );
    expect(result.items[0]).toMatchObject({ id: 31, cost: 8, spent: 2 });
    expect(result.capabilities.canResolve).toBe(true);
  });
});
