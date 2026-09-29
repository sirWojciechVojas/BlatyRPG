import { describe, expect, it, vi } from "vitest";
import { createSceneApiClient } from "@/lib/vtt/sceneApiClient";
import { cloneSceneDraft } from "@/lib/vtt/sceneNormalizer";

const apiScene = {
  id: 4,
  campaign_id: 7,
  name: "Ruins",
  background_url: "https://example.test/ruins.webp",
  width: 1600,
  height: 900,
  grid_type: "hex_pointy",
  grid_size: 72,
  darkness_level: "0.650",
  global_illumination: 1,
  fog_exploration: 0,
  revision: 3,
};

describe("sceneApiClient", () => {
  it("clones only writable scene data", () => {
    expect(
      cloneSceneDraft(
        {
          ...apiScene,
          backgroundUrl: apiScene.background_url,
          sortOrder: 4,
          revision: 9,
        },
        "Ruins — copy",
      ),
    ).toMatchObject({
      name: "Ruins — copy",
      backgroundUrl: apiScene.background_url,
      width: 1600,
      height: 900,
      sortOrder: 5,
    });
    expect(
      cloneSceneDraft({ ...apiScene, revision: 9 }, "Copy"),
    ).not.toHaveProperty("revision");
  });

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
      globalLightLevel: 0.883,
      globalIllumination: true,
      fogExploration: false,
    });
    expect(result.scene.darknessLevel).toBeCloseTo(0.117);
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
