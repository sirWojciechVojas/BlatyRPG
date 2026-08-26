import { describe, expect, it } from "vitest";
import {
  clampTokenSelectionPoint,
  pointInPolygon,
  selectableTokensInArea,
} from "../tokenAreaSelection";

const token = (id, x, y, canControl = true) => ({
  id,
  x,
  y,
  width: 20,
  height: 20,
  capabilities: { canControl },
});

describe("token area selection geometry", () => {
  it("selects controllable token centers inside a rectangle", () => {
    const selected = selectableTokensInArea(
      [token(1, 10, 10), token(2, 90, 90), token(3, 20, 20, false)],
      {
        type: "rectangle",
        start: { x: 0, y: 0 },
        current: { x: 50, y: 50 },
      },
    );
    expect(selected.map(({ id }) => id)).toEqual([1]);
  });

  it("selects token centers inside a circle", () => {
    const selected = selectableTokensInArea(
      [token(1, 40, 40), token(2, 80, 80)],
      {
        type: "circle",
        start: { x: 50, y: 50 },
        current: { x: 80, y: 50 },
      },
    );
    expect(selected.map(({ id }) => id)).toEqual([1]);
  });

  it("supports polygon boundaries and live cursor point", () => {
    const selection = {
      type: "polygon",
      points: [
        { x: 0, y: 0 },
        { x: 100, y: 0 },
      ],
      current: { x: 0, y: 100 },
    };
    expect(
      pointInPolygon({ x: 10, y: 10 }, [
        ...selection.points,
        selection.current,
      ]),
    ).toBe(true);
    expect(selectableTokensInArea([token(1, 0, 0)], selection)).toHaveLength(1);
  });

  it("clamps pointer coordinates to scene bounds", () => {
    expect(
      clampTokenSelectionPoint({ x: -20, y: 700 }, { width: 500, height: 400 }),
    ).toEqual({
      x: 0,
      y: 400,
    });
  });
});
