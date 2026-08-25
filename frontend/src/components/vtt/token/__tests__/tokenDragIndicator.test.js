import { describe, expect, it } from "vitest";
import { buildTokenDragIndicator } from "../tokenDragIndicator";

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
    });
  });
});
