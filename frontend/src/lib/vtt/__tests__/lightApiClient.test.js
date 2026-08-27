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
  clarity: "0.350",
  source_type: "darkness",
  animation: "pulse",
  enabled: 1,
  revision: 2,
  capabilities: { canManage: true },
};

describe("lightApiClient", () => {
  it("keeps existing scenes haze-free when clarity is absent", async () => {
    const legacyLight = { ...apiLight };
    delete legacyLight.clarity;
    const client = createLightApiClient({
      request: vi.fn().mockResolvedValue({ items: [legacyLight] }),
    });

    const result = await client.list(7, 4);

    expect(result.items[0].clarity).toBe(0);
  });

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
      lumens: 640,
      name: "Light",
      enabled: true,
      opacity: 0.7,
      clarity: 0.35,
      sourceType: "darkness",
      animation: "pulse",
    });
  });

  it("whitelists writes and includes optimistic revision", async () => {
    const request = vi.fn().mockResolvedValue({ light: apiLight });
    const client = createLightApiClient({ request });

    await client.update(7, 4, 6, {
      id: 99,
      lumens: 1200,
      direction: 45,
      angle: 60,
      sourceType: "cone",
      opacity: 0.6,
      clarity: 0.45,
      providesVision: true,
      revision: 2,
    });
    expect(request).toHaveBeenCalledWith("/campaigns/7/scenes/4/lights/6", {
      method: "PATCH",
      body: {
        lumens: 1200,
        direction: 45,
        angle: 60,
        sourceType: "cone",
        opacity: 0.6,
        clarity: 0.45,
        providesVision: true,
        revision: 2,
      },
    });
  });
});
