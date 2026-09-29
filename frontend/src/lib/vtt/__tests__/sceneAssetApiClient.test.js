import { describe, expect, it, vi } from "vitest";
import {
  createSceneAssetApiClient,
  sceneAssetLocation,
} from "@/lib/vtt/sceneAssetApiClient";

describe("sceneAssetApiClient", () => {
  it("recognizes protected scene asset URLs", () => {
    expect(
      sceneAssetLocation("/api/campaigns/7/scene-assets/map-1.webp/file"),
    ).toEqual({ campaignId: 7, assetKey: "map-1.webp" });
    expect(sceneAssetLocation("https://cdn.example.test/map.webp")).toBeNull();
  });

  it("lists campaign-scoped background assets", async () => {
    const request = vi.fn().mockResolvedValue({ items: [{ key: "map.webp" }] });
    const client = createSceneAssetApiClient({ client: { request } });

    await expect(client.list(7)).resolves.toEqual([{ key: "map.webp" }]);
    expect(request).toHaveBeenCalledWith("/campaigns/7/scene-assets");
  });
});
