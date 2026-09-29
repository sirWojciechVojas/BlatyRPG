import { describe, expect, it } from "vitest";
import {
  tokenMovementPreview,
  tokenMovementRouteCost,
  tokenMovementState,
} from "@/lib/vtt/tokenMovement";

describe("token movement cost", () => {
  it("counts square movement in cells, including diagonals", () => {
    const scene = { gridType: "square", gridSize: 100 };
    expect(
      tokenMovementRouteCost(scene, [
        { x: 50, y: 50 },
        { x: 250, y: 150 },
      ]),
    ).toBe(2);
  });

  it("adds waypoint segments on a hex grid", () => {
    const scene = { gridType: "hex_pointy", gridSize: 100 };
    expect(
      tokenMovementRouteCost(scene, [
        { x: 0, y: 0 },
        { x: 100, y: 0 },
        { x: 150, y: 86.603 },
      ]),
    ).toBe(2);
  });

  it("projects spent points and marks an exceeded range", () => {
    const preview = tokenMovementPreview(
      { gridType: "square", gridSize: 100 },
      {
        x: 0,
        y: 0,
        width: 100,
        height: 100,
        movementSpent: 4,
        movementRange: 6,
      },
      { x: 300, y: 0 },
    );
    expect(preview).toMatchObject({ cost: 3, projected: 7, exceeded: true });
  });

  it("derives available points from the authoritative range and spent values", () => {
    expect(
      tokenMovementState({
        movementRange: 8,
        movementSpent: 2.5,
        movementPoints: 99,
      }),
    ).toEqual({ range: 8, spent: 2.5, remaining: 5.5 });
  });
});
