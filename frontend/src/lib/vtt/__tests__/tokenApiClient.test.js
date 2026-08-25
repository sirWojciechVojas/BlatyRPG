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
  rotation: "370.000",
  facing: "-15.000",
  rotationHandleEnabled: true,
  facingHandleEnabled: false,
  visibleTo: { mode: "users", userIds: [7, 4] },
  controlledBy: { mode: "everyone", userIds: [] },
  editableBy: { mode: "gm", userIds: [] },
  observerBy: { mode: "inherit", userIds: [] },
  resources: {
    bars: [{ enabled: true, label: "HP", value: 8, max: 10 }],
  },
  revision: 3,
  capabilities: {
    canControl: true,
    canEdit: false,
    canObserve: true,
    canManage: false,
  },
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
      rotation: 10,
      facing: 345,
      rotationHandleEnabled: true,
      facingHandleEnabled: false,
      visibleTo: { mode: "users", userIds: [4, 7] },
      controlledBy: { mode: "everyone", userIds: [] },
      capabilities: {
        canControl: true,
        canEdit: false,
        canObserve: true,
        canManage: false,
      },
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
      facing: 90,
      rotationHandleEnabled: true,
      statuses: ["poisoned", "stunned"],
      observerBy: { mode: "users", userIds: [12] },
      resources: apiToken.resources,
      revision: 3,
    });
    expect(request).toHaveBeenCalledWith("/campaigns/7/scenes/4/tokens/9", {
      method: "PATCH",
      body: {
        x: 300,
        y: 400,
        facing: 90,
        rotationHandleEnabled: true,
        statuses: ["poisoned", "stunned"],
        observerBy: { mode: "users", userIds: [12] },
        resources: expect.objectContaining({
          bars: expect.arrayContaining([
            expect.objectContaining({ label: "HP", value: 8, max: 10 }),
          ]),
        }),
        revision: 3,
      },
    });
  });
});
