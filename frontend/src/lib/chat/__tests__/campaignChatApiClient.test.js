import { describe, expect, it, vi } from "vitest";
import {
  createCampaignChatApiClient,
  normalizeChatMessage,
} from "@/lib/chat/campaignChatApiClient";

describe("campaignChatApiClient", () => {
  it("uses cursor pagination and normalizes messages", async () => {
    const request = vi.fn().mockResolvedValue({
      items: [
        {
          id: "7",
          campaign_id: "3",
          body: "Hello",
          author: { id: "2", name: "Ada", isCurrentUser: true },
          created_at: "2026-08-19 12:00:00",
        },
      ],
      pagination: { afterId: 7, hasMoreAfter: false },
      capabilities: { canRead: true, canSend: true },
      sync: { transport: "polling", recommendedIntervalMs: 4000 },
    });
    const api = createCampaignChatApiClient({ request });

    const result = await api.list(3, { afterId: 4, limit: 50 });

    expect(request).toHaveBeenCalledWith(
      "/campaigns/3/chat/messages?afterId=4&limit=50",
      {},
    );
    expect(result.items[0]).toMatchObject({
      id: 7,
      campaignId: 3,
      body: "Hello",
      author: { id: 2, name: "Ada", isCurrentUser: true },
    });
    expect(result.sync.transport).toBe("polling");
  });

  it("sends only text and an idempotency nonce", async () => {
    const request = vi.fn().mockResolvedValue({
      message: { id: 8, body: "Ready", author: { name: "Ada" } },
      capabilities: { canSend: true },
      duplicate: false,
    });
    const api = createCampaignChatApiClient({ request });

    await api.send(3, "Ready", "550e8400-e29b-41d4-a716-446655440000");

    expect(request).toHaveBeenCalledWith("/campaigns/3/chat/messages", {
      method: "POST",
      body: {
        body: "Ready",
        clientNonce: "550e8400-e29b-41d4-a716-446655440000",
      },
    });
  });

  it("does not trust unknown message ids", () => {
    expect(normalizeChatMessage({ id: "nope" }).id).toBeNull();
  });
});
