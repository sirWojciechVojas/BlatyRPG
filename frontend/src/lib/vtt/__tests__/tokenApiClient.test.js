import { describe, expect, it, vi } from "vitest";
import { createTokenApiClient } from "@/lib/vtt/tokenApiClient";

const apiToken = {
  id: 9,
  scene_id: 4,
  character_id: 12,
  name: "Guard",
  image_url: "/guard.webp",
  x: "120.500",
  y: "240.000",
  width: "80.000",
  height: "80.000",
  revision: 3,
  capabilities: { canControl: true, canManage: false },
};

describe("tokenApiClient", () => {
  it("normalizes scene tokens and capabilities", async () => {
    const request = vi.fn().mockResolvedValue({
      items: [apiToken],
      capabilities: { canCreate: false },
    });
    const client = createTokenApiClient({ request });

    const result = await client.list(7, 4);
    expect(request).toHaveBeenCalledWith("/campaigns/7/scenes/4/tokens", {});
    expect(result.items[0]).toMatchObject({
      id: 9,
      sceneId: 4,
      characterId: 12,
      imageUrl: "/guard.webp",
      x: 120.5,
      capabilities: { canControl: true, canManage: false },
    });
  });

  it("whitelists writes and sends optimistic revisions", async () => {
    const request = vi.fn().mockResolvedValue({ token: apiToken });
    const client = createTokenApiClient({ request });

    await client.update(7, 4, 9, {
      id: 55,
      campaignId: 90,
      x: 300,
      y: 400,
      revision: 3,
    });
    expect(request).toHaveBeenCalledWith("/campaigns/7/scenes/4/tokens/9", {
      method: "PATCH",
      body: { x: 300, y: 400, revision: 3 },
    });
  });
});
