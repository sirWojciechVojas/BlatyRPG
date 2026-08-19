import { describe, expect, it } from "vitest";
import { buildGridPattern, GRID_TYPES } from "@/lib/vtt/grid";

describe("VTT grid geometry", () => {
  it("builds an offset square pattern", () => {
    const pattern = buildGridPattern({
      gridType: GRID_TYPES.SQUARE,
      gridSize: 80,
      gridOffsetX: 12,
      gridOffsetY: -4,
      gridColor: "#abcdef",
      gridOpacity: 0.6,
    });
    expect(pattern).toMatchObject({
      width: 80,
      height: 80,
      offsetX: 12,
      offsetY: -4,
      color: "#abcdef",
      opacity: 0.6,
    });
    expect(pattern.path).toBe("M 80 0 H 0 V 80");
  });

  it.each([GRID_TYPES.HEX_POINTY, GRID_TYPES.HEX_FLAT])(
    "builds a repeatable %s hex pattern",
    (gridType) => {
      const pattern = buildGridPattern({ gridType, gridSize: 100 });
      expect(pattern.width).toBeGreaterThan(100);
      expect(pattern.height).toBeGreaterThan(100);
      expect(pattern.path).toContain("M ");
      expect(pattern.path).toContain(" Z");
    },
  );

  it("does not render a pattern for a gridless scene", () => {
    expect(buildGridPattern({ gridType: GRID_TYPES.GRIDLESS })).toBeNull();
  });
});
