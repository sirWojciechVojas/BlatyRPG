import { describe, expect, it, vi } from "vitest";
import { createTileApiClient } from "@/lib/vtt/tileApiClient";

const apiTile = {
  id: 9,
  scene_id: 4,
  name: "Campfire",
  asset_url: "/assets/fire.webm",
  media_type: "video",
  layer: "foreground",
  x: "200.000",
  y: "300.000",
  width: "160.000",
  height: "160.000",
  rotation: "15.000",
  opacity: "0.800",
  sort_order: 2,
  loop: 1,
  muted: 1,
  revision: 2,
};

describe("tileApiClient", () => {
  it("normalizes tile collections", async () => {
    const request = vi.fn().mockResolvedValue({
      items: [apiTile],
      capabilities: { canManage: true },
    });
    const result = await createTileApiClient({ request }).list(7, 4);

    expect(request).toHaveBeenCalledWith("/campaigns/7/scenes/4/tiles", {});
    expect(result.items[0]).toMatchObject({
      id: 9,
      sceneId: 4,
      mediaType: "video",
      width: 160,
      opacity: 0.8,
      loop: true,
    });
  });

  it("whitelists writes and includes optimistic revision", async () => {
    const request = vi.fn().mockResolvedValue({ tile: apiTile });
    const client = createTileApiClient({ request });

    await client.update(7, 4, 9, {
      id: 99,
      layer: "background",
      opacity: 0.5,
      revision: 2,
    });
    expect(request).toHaveBeenCalledWith("/campaigns/7/scenes/4/tiles/9", {
      method: "PATCH",
      body: { layer: "background", opacity: 0.5, revision: 2 },
    });
  });
});
