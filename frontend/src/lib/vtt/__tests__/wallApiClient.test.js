import { describe, expect, it, vi } from "vitest";
import { createWallApiClient } from "@/lib/vtt/wallApiClient";

const apiWall = {
  id: 8,
  scene_id: 4,
  type: "door",
  name: "North gate",
  x1: "100.000",
  y1: "50.000",
  x2: "200.000",
  y2: "50.000",
  blocks_movement: 1,
  door_state: "closed",
  color: "#33AAFF",
  enabled: 1,
  hidden: 0,
  revision: 2,
  capabilities: { canManage: true },
};

describe("wallApiClient", () => {
  it("normalizes wall collections", async () => {
    const request = vi.fn().mockResolvedValue({
      items: [apiWall],
      capabilities: { canManage: true },
    });
    const client = createWallApiClient({ request });

    const result = await client.list(7, 4);
    expect(request).toHaveBeenCalledWith("/campaigns/7/scenes/4/walls", {});
    expect(result.items[0]).toMatchObject({
      id: 8,
      sceneId: 4,
      type: "door",
      name: "North gate",
      x1: 100,
      blocksMovement: true,
      doorState: "closed",
      color: "#33AAFF",
      enabled: true,
      hidden: false,
    });
  });

  it("whitelists updates and includes optimistic revision", async () => {
    const request = vi.fn().mockResolvedValue({ wall: apiWall });
    const client = createWallApiClient({ request });

    await client.update(7, 4, 8, {
      id: 99,
      doorState: "open",
      enabled: false,
      revision: 2,
    });
    expect(request).toHaveBeenCalledWith("/campaigns/7/scenes/4/walls/8", {
      method: "PATCH",
      body: { doorState: "open", enabled: false, revision: 2 },
    });
  });

  it("sends selected token context with a door interaction", async () => {
    const request = vi.fn().mockResolvedValue({ wall: apiWall });
    const client = createWallApiClient({ request });

    await client.interact(7, 4, 8, 2, "open", [21], true);

    expect(request).toHaveBeenCalledWith(
      "/campaigns/7/scenes/4/walls/8/interact",
      {
        method: "POST",
        body: {
          revision: 2,
          doorState: "open",
          actingTokenIds: [21],
          silent: true,
        },
      },
    );
  });
});
