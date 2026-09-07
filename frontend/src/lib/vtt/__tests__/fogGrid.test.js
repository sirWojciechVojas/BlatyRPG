import { describe, expect, it } from "vitest";
import {
  cellAtPoint,
  fogGrid,
  markBrush,
  maskFromRanges,
  rangesFromMask,
} from "@/lib/vtt/fogGrid";
import { computeFogVisibility } from "@/lib/vtt/fogVisibility";

const scene = {
  width: 512,
  height: 256,
  gridSize: 32,
  globalLightLevel: 1,
};
const token = (x, id = 1) => ({
  id,
  x,
  y: 96,
  width: 32,
  height: 32,
  facing: 0,
  elevation: 0,
  capabilities: { canControl: true },
  vision: {
    enabled: true,
    range: 400,
    angle: 360,
    constrainedByWalls: true,
    limitByLight: true,
  },
});

describe("Fog of War grid", () => {
  it("round-trips compact exploration ranges", () => {
    const mask = maskFromRanges(20, [
      [1, 4],
      [7, 7],
      [8, 10],
    ]);
    expect(rangesFromMask(mask)).toEqual([
      [1, 4],
      [7, 10],
    ]);
  });

  it("bounds the raster size for very large scenes", () => {
    const large = fogGrid({ width: 100000, height: 100000 }, 32);
    expect(large.length).toBeLessThanOrEqual(1048576);
  });

  it("uses brush size and hardness without writing outside the grid", () => {
    const grid = fogGrid(scene, 32);
    const hard = new Uint8Array(grid.length);
    const soft = new Uint8Array(grid.length);
    markBrush(hard, grid, { x: 256, y: 128 }, 192, 1);
    markBrush(soft, grid, { x: 256, y: 128 }, 192, 0);
    const hardCells = hard.reduce((sum, value) => sum + value, 0);
    const softCells = soft.reduce((sum, value) => sum + value, 0);
    expect(hardCells).toBeGreaterThan(0);
    expect(softCells).toBeGreaterThan(0);
    expect(softCells).toBeLessThan(hardCells);
  });

  it("blocks sight behind a closed door and reveals it immediately when opened", () => {
    const grid = fogGrid(scene, 32);
    const door = {
      x1: 160,
      y1: 0,
      x2: 160,
      y2: 256,
      enabled: true,
      type: "door",
      doorState: "closed",
      blocksSight: true,
      blocksLight: true,
    };
    const target = cellAtPoint(grid, { x: 240, y: 112 });
    const closedResult = computeFogVisibility({
      scene,
      tokens: [token(64)],
      walls: [door],
      grid,
    });
    const openResult = computeFogVisibility({
      scene,
      tokens: [token(64)],
      walls: [{ ...door, doorState: "open" }],
      grid,
    });
    expect(closedResult.visible[target]).toBe(0);
    expect(openResult.visible[target]).toBe(1);
    expect(closedResult.sources[0].polygon.length).toBeGreaterThan(3);
    expect(closedResult.illuminationGeometry.global).toBe(true);
  });

  it("produces independent masks for players in different positions", () => {
    const grid = fogGrid(scene, 32);
    const left = computeFogVisibility({
      scene,
      tokens: [token(0, 1)],
      grid,
    }).visible;
    const right = computeFogVisibility({
      scene,
      tokens: [token(448, 2)],
      grid,
    }).visible;
    expect(left[cellAtPoint(grid, { x: 32, y: 112 })]).toBe(1);
    expect(right[cellAtPoint(grid, { x: 32, y: 112 })]).toBe(0);
    expect(right[cellAtPoint(grid, { x: 480, y: 112 })]).toBe(1);
  });
});
