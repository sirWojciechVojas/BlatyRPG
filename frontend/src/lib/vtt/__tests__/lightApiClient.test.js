import { describe, expect, it, vi } from "vitest";
import { createLightApiClient } from "@/lib/vtt/lightApiClient";

const apiLight = {
  id: 6,
  scene_id: 4,
  x: "200.000",
  y: "300.000",
  bright_radius: "150.000",
  dim_radius: "350.000",
  color: "#FFD27A",
  intensity: "0.800",
  opacity: "0.700",
  source_type: "darkness",
  animation: "pulse",
  enabled: 1,
  revision: 2,
  capabilities: { canManage: true },
};

describe("lightApiClient", () => {
  it("normalizes light collections", async () => {
    const request = vi.fn().mockResolvedValue({
      items: [apiLight],
      capabilities: { canManage: true },
    });
    const client = createLightApiClient({ request });

    const result = await client.list(7, 4);
    expect(request).toHaveBeenCalledWith("/campaigns/7/scenes/4/lights", {});
    expect(result.items[0]).toMatchObject({
      id: 6,
      sceneId: 4,
      brightRadius: 150,
      dimRadius: 350,
      intensity: 0.8,
      enabled: true,
      opacity: 0.7,
      sourceType: "darkness",
      animation: "pulse",
    });
  });

  it("whitelists writes and includes optimistic revision", async () => {
    const request = vi.fn().mockResolvedValue({ light: apiLight });
    const client = createLightApiClient({ request });

    await client.update(7, 4, 6, {
      id: 99,
      intensity: 0.5,
      opacity: 0.6,
      providesVision: true,
      revision: 2,
    });
    expect(request).toHaveBeenCalledWith("/campaigns/7/scenes/4/lights/6", {
      method: "PATCH",
      body: {
        intensity: 0.5,
        opacity: 0.6,
        providesVision: true,
        revision: 2,
      },
    });
  });
});
