import { describe, expect, it } from "vitest";
import { buildTokenMovementRange } from "@/lib/vtt/tokenMovementRange";

const token = (changes = {}) => ({
  x: 100,
  y: 100,
  width: 100,
  height: 100,
  movementRange: 6,
  movementSpent: 4,
  ...changes,
});

describe("token movement range", () => {
  it("builds an exact square-cell area from remaining movement points", () => {
    const range = buildTokenMovementRange(
      { gridType: "square", gridSize: 100 },
      token(),
    );

    expect(range).toMatchObject({
      range: 6,
      spent: 4,
      remaining: 2,
      origin: { x: 150, y: 150 },
    });
    expect(range.path).toBe("M -100 -100 L 400 -100 L 400 400 L -100 400 Z");
  });

  it.each(["hex_pointy", "hex_flat"])(
    "contains seven cells for one remaining point on %s",
    (gridType) => {
      const range = buildTokenMovementRange(
        { gridType, gridSize: 100 },
        token({ x: -50, y: -50, movementRange: 1, movementSpent: 0 }),
      );

      expect(range.path.match(/\bM\b/gu)).toHaveLength(7);
    },
  );

  it("uses grid size as the movement unit on a gridless scene", () => {
    const range = buildTokenMovementRange(
      { gridType: "gridless", gridSize: 80 },
      token({ movementRange: 2.5, movementSpent: 1 }),
    );

    expect(range.remaining).toBe(1.5);
    expect(range.path).toContain("a 120 120");
  });

  it("does not enumerate thousands of hex cells for a very large range", () => {
    const range = buildTokenMovementRange(
      { gridType: "hex_pointy", gridSize: 20 },
      token({ movementRange: 10000, movementSpent: 0 }),
    );

    expect(range.path.length).toBeLessThan(2000);
  });
});
