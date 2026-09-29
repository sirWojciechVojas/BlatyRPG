import { describe, expect, it } from "vitest";
import {
  exceededGroupTokens,
  tokenDragGroup,
  tokenGroupBlockers,
  tokenGroupMovementPreviews,
} from "../tokenGroupMovement";

const token = (id, x, points = 6) => ({
  id,
  x,
  y: 0,
  width: 100,
  height: 100,
  movementRange: 6,
  movementSpent: 6 - points,
  movementPoints: points,
  capabilities: { canControl: true, canManage: false },
});

describe("group token movement", () => {
  it("uses the existing multi-selection only when dragging its member", () => {
    const tokens = [token(1, 0), token(2, 200), token(3, 400)];

    expect(tokenDragGroup(tokens, [1, 2], tokens[0])).toHaveLength(2);
    expect(tokenDragGroup(tokens, [1, 2], tokens[2])).toEqual([tokens[2]]);
  });

  it("moves every token by the same snapped grid displacement", () => {
    const tokens = [token(1, 0), token(2, 200)];
    const previews = tokenGroupMovementPreviews(
      { gridType: "square", gridSize: 100 },
      tokens,
      tokens[0],
      { x: 200, y: 100 },
    );

    expect(previews.map(({ position }) => position)).toEqual([
      { x: 200, y: 100 },
      { x: 400, y: 100 },
    ]);
    expect(previews.map(({ movement }) => movement.cost)).toEqual([2, 2]);
  });

  it("blocks the whole group when control or movement points are missing", () => {
    const depleted = token(2, 100, 0);
    const uncontrolled = {
      ...token(3, 200),
      capabilities: { canControl: false, canManage: false },
    };

    expect(tokenGroupBlockers([token(1, 0), depleted, uncontrolled])).toEqual([
      depleted,
      uncontrolled,
    ]);
  });

  it("reports every non-GM token whose projected cost exceeds its PR", () => {
    const tokens = [token(1, 0, 3), token(2, 100, 1)];
    const previews = tokenGroupMovementPreviews(
      { gridType: "square", gridSize: 100 },
      tokens,
      tokens[0],
      { x: 200, y: 0 },
    );

    expect(exceededGroupTokens(previews)).toEqual([tokens[1]]);
  });
});
