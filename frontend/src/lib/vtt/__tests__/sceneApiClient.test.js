import { describe, expect, it, vi } from "vitest";
import { createSceneApiClient } from "@/lib/vtt/sceneApiClient";

const apiScene = {
  id: 4,
  campaign_id: 7,
  name: "Ruins",
  background_url: "https://example.test/ruins.webp",
  width: 1600,
  height: 900,
  grid_type: "hex_pointy",
  grid_size: 72,
  revision: 3,
};

describe("sceneApiClient", () => {
  it("uses campaign routes and normalizes scene snapshots", async () => {
    const request = vi.fn().mockResolvedValue({
      scene: apiScene,
      capabilities: { canManage: true, canViewHidden: true },
    });
    const client = createSceneApiClient({ request });

    const result = await client.get(7, 4);
    expect(request).toHaveBeenCalledWith("/campaigns/7/scenes/4", {});
    expect(result.scene).toMatchObject({
      campaignId: 7,
      backgroundUrl: "https://example.test/ruins.webp",
      gridType: "hex_pointy",
      gridSize: 72,
    });
  });

  it("whitelists update fields and sends the optimistic revision", async () => {
    const request = vi.fn().mockResolvedValue({
      scene: apiScene,
      capabilities: {},
    });
    const client = createSceneApiClient({ request });

    await client.update(7, 4, {
      id: 99,
      name: "Updated ruins",
      gridType: "square",
      revision: 3,
    });
    expect(request).toHaveBeenCalledWith("/campaigns/7/scenes/4", {
      method: "PATCH",
      body: {
        name: "Updated ruins",
        grid_type: "square",
        revision: 3,
      },
    });
  });

  it("sends revisions when activating and deleting", async () => {
    const request = vi
      .fn()
      .mockResolvedValueOnce({
        scene: apiScene,
        activeSceneId: 4,
        capabilities: {},
      })
      .mockResolvedValueOnce({ active_scene_id: 8 });
    const client = createSceneApiClient({ request });

    await client.activate(7, 4, 3);
    const deletion = await client.remove(7, 4, 3);
    expect(request).toHaveBeenNthCalledWith(
      1,
      "/campaigns/7/scenes/4/activate",
      {
        method: "POST",
        body: { revision: 3 },
      },
    );
    expect(request).toHaveBeenNthCalledWith(2, "/campaigns/7/scenes/4", {
      method: "DELETE",
      body: { revision: 3 },
    });
    expect(deletion).toEqual({ activeSceneId: 8 });
  });
});
