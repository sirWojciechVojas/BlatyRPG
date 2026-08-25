import { describe, expect, it } from "vitest";
import {
  buildGridPattern,
  GRID_TYPES,
  snapPointToGrid,
  snapTokenPosition,
} from "@/lib/vtt/grid";

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

  it("snaps the center of differently sized tokens to a square cell", () => {
    const scene = {
      gridType: GRID_TYPES.SQUARE,
      gridSize: 100,
      gridOffsetX: 10,
      gridOffsetY: 20,
    };

    expect(
      snapTokenPosition(scene, { x: 72, y: 83 }, { width: 100, height: 100 }),
    ).toEqual({ x: 110, y: 120 });
    expect(
      snapTokenPosition(scene, { x: 72, y: 83 }, { width: 200, height: 100 }),
    ).toEqual({ x: 60, y: 120 });
  });

  it("snaps quarter-cell tokens without inflating their dimensions", () => {
    expect(
      snapTokenPosition(
        { gridType: GRID_TYPES.SQUARE, gridSize: 4 },
        { x: 2.6, y: 2.6 },
        { width: 1, height: 1 },
      ),
    ).toEqual({ x: 1.5, y: 1.5 });
  });

  it.each([
    [GRID_TYPES.HEX_POINTY, { x: 147, y: 90 }, { x: 150, y: 86.603 }],
    [GRID_TYPES.HEX_FLAT, { x: 90, y: 147 }, { x: 86.603, y: 150 }],
  ])("snaps a point to the nearest %s center", (gridType, point, expected) => {
    expect(snapPointToGrid({ gridType, gridSize: 100 }, point)).toEqual(
      expected,
    );
  });

  it("preserves free movement on gridless scenes", () => {
    expect(
      snapTokenPosition(
        { gridType: GRID_TYPES.GRIDLESS },
        { x: 17.25, y: 44.75 },
        { width: 160, height: 80 },
      ),
    ).toEqual({ x: 17.25, y: 44.75 });
  });
});
