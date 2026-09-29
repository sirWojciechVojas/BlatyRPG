import { describe, expect, it } from "vitest";
import {
  buildTokenDragIndicator,
  tokenGhostStyle,
} from "../tokenDragIndicator";

describe("token drag indicator", () => {
  it("identifies the token and reports distance in scene units", () => {
    const indicator = buildTokenDragIndicator(
      { gridSize: 100, gridDistance: 5, gridUnit: "m" },
      {
        name: "Eryk Młody",
        imageUrl: "/eryk.webp",
        x: 100,
        y: 200,
        width: 100,
        height: 100,
        movementRange: 6,
        movementSpent: 1,
      },
      { x: 300, y: 200 },
    );

    expect(indicator).toMatchObject({
      name: "Eryk Młody",
      initials: "EM",
      imageUrl: "/eryk.webp",
      start: { x: 150, y: 250 },
      end: { x: 350, y: 250 },
      distance: "10 m",
      movement: "3 / 6 PR",
      exceeded: false,
    });
  });

  it("builds a waypoint polyline and marks an exceeded route", () => {
    const indicator = buildTokenDragIndicator(
      { gridType: "square", gridSize: 100, gridDistance: 5, gridUnit: "m" },
      { x: 0, y: 0, width: 100, height: 100, movementRange: 2 },
      { x: 300, y: 0 },
      [{ x: 100, y: 100 }],
    );

    expect(indicator.waypoints).toEqual([{ x: 150, y: 150 }]);
    expect(indicator.polyline).toBe("50,50 150,150 350,50");
    expect(indicator.exceeded).toBe(true);
  });

  it("places the drag ghost at the planned destination", () => {
    expect(
      tokenGhostStyle({ end: { x: 350, y: 250 }, width: 100, height: 80 }),
    ).toMatchObject({ left: "300px", top: "210px" });
  });
});
