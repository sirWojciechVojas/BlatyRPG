import { describe, expect, it, vi } from "vitest";
import { createSceneApiClient } from "@/lib/vtt/sceneApiClient";
import { normalizeScene, toSceneWritePayload } from "@/lib/vtt/sceneNormalizer";

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

  it("uses the dedicated endpoint for full scene duplication", async () => {
    const request = vi.fn().mockResolvedValue({
      scene: { ...apiScene, id: 5, name: "Ruins — copy" },
      capabilities: {},
    });
    const client = createSceneApiClient({ request });

    const result = await client.duplicate(7, 4, " Ruins — copy ");

    expect(request).toHaveBeenCalledWith("/campaigns/7/scenes/4/duplicate", {
      method: "POST",
      body: { name: "Ruins — copy" },
    });
    expect(result.scene).toMatchObject({ id: 5, name: "Ruins — copy" });
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

  it("round-trips every scene settings field through the API contract", () => {
    const draft = {
      name: "Flooded vault",
      description: "Lower level",
      backgroundUrl:
        "/api/campaigns/7/scene-assets/0123456789abcdef0123456789abcdef.webp/file",
      width: 2400,
      height: 1600,
      padding: 64,
      backgroundColor: "#15202AFF",
      gridType: "hex_flat",
      gridSize: 84,
      gridDistance: 2.5,
      gridUnit: "m",
      gridOffsetX: 11.5,
      gridOffsetY: -7,
      gridColor: "#C4A45AFF",
      gridOpacity: 0.47,
      globalLightLevel: 0.35,
      fogExploration: true,
      fogEnabled: true,
      dynamicVision: false,
      explorationMemory: true,
      fogUnexploredColor: "#05070BFF",
      fogUnexploredOpacity: 0.91,
      fogExploredOpacity: 0.38,
      fogEdgeSoftness: 140,
      fogUpdateDuringDrag: false,
      isVisible: false,
      sortOrder: -4,
    };
    const payload = toSceneWritePayload(draft);
    const reloaded = normalizeScene({
      id: 4,
      campaign_id: 7,
      revision: 9,
      ...payload,
      is_visible: 0,
    });

    expect(Object.keys(payload)).toHaveLength(27);
    expect(reloaded).toMatchObject(draft);
    expect(reloaded.fogEdgeSoftness).toBe(140);
  });
});
