import { describe, expect, it } from "vitest";
import { wallLength, wallMidpoint, wallPoint } from "@/lib/vtt/wallGeometry";

const scene = { width: 1000, height: 500, gridSize: 100 };
const surface = {
  getBoundingClientRect: () => ({ left: 10, top: 20, width: 500, height: 250 }),
};

describe("wall geometry", () => {
  it("converts viewport coordinates and snaps to half-grid points", () => {
    expect(wallPoint({ clientX: 139, clientY: 81 }, surface, scene)).toEqual({
      x: 250,
      y: 100,
    });
  });

  it("calculates segment length and midpoint", () => {
    const wall = { x1: 10, y1: 20, x2: 40, y2: 60 };
    expect(wallLength(wall)).toBe(50);
    expect(wallMidpoint(wall)).toEqual({ x: 25, y: 40 });
  });
});
