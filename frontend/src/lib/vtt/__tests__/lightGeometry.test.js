import { describe, expect, it } from "vitest";
import {
  lightPolygonPath,
  lightPolygonPoints,
  wallBlocksLight,
} from "../lightGeometry";

const scene = { width: 1000, height: 1000 };
const light = { x: 100, y: 100, dimRadius: 100 };
const wall = {
  type: "wall",
  x1: 150,
  y1: 0,
  x2: 150,
  y2: 200,
  blocksLight: true,
};

describe("light visibility polygon", () => {
  it("clips a ray at the nearest blocking wall", () => {
    const points = lightPolygonPoints(light, [wall], scene);
    const east = points.find((point) => point.y === 100 && point.x >= 100);
    expect(east.x).toBe(150);
  });

  it("allows light through an open door", () => {
    const door = { ...wall, type: "door", doorState: "open" };
    const points = lightPolygonPoints(light, [door], scene);
    const east = points.find((point) => point.y === 100 && point.x >= 100);
    expect(wallBlocksLight(door)).toBe(false);
    expect(east.x).toBe(200);
  });

  it("builds a closed SVG path", () => {
    expect(lightPolygonPath(light, [wall], scene)).toMatch(/^M .+ Z$/);
  });
});
