import { describe, expect, it, vi } from "vitest";
import {
  readDroppedTileAsset,
  tileDraftFromAsset,
  writeTileAssetDrag,
} from "../tileDrop";

describe("tile asset drag and drop", () => {
  it("serializes a video asset without executable metadata", () => {
    let serialized = "";
    const transfer = {
      setData: vi.fn((_type, value) => {
        serialized = value;
      }),
      getData: vi.fn(() => serialized),
    };
    writeTileAssetDrag(transfer, {
      label: "Fire",
      url: "https://cdn.example.test/fire.webm",
      ignored: "client-state",
    });

    expect(readDroppedTileAsset(transfer)).toEqual({
      name: "Fire",
      assetUrl: "https://cdn.example.test/fire.webm",
      mediaType: "video",
    });
  });

  it("clamps a dropped tile origin to the scene", () => {
    expect(
      tileDraftFromAsset(
        { name: "Crate", assetUrl: "/crate.webp", mediaType: "image" },
        { x: -20, y: 1200 },
        { width: 1000, height: 800, gridSize: 50 },
      ),
    ).toMatchObject({ x: 0, y: 800, width: 200, height: 200 });
  });
});
