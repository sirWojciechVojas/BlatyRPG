import { describe, expect, it } from "vitest";
import {
  cloneRegionPolygons,
  insertRegionVertex,
  regionEdgeHandles,
  removeRegionVertex,
  replaceRegionVertex,
} from "@/lib/vtt/regionVertexGeometry";

const square = [
  [
    { x: 0, y: 0 },
    { x: 100, y: 0 },
    { x: 100, y: 100 },
    { x: 0, y: 100 },
  ],
];

describe("region vertex geometry", () => {
  it("clones coordinates without retaining mutable point references", () => {
    const clone = cloneRegionPolygons(square);
    clone[0][0].x = 20;
    expect(square[0][0].x).toBe(0);
  });

  it("inserts a vertex immediately after the selected edge", () => {
    const result = insertRegionVertex(square, 0, 1, { x: 100, y: 50 });
    expect(result[0]).toHaveLength(5);
    expect(result[0][2]).toEqual({ x: 100, y: 50 });
  });

  it("moves and removes vertices while preserving a valid polygon", () => {
    const moved = replaceRegionVertex(square, 0, 2, { x: 80, y: 90 });
    expect(moved[0][2]).toEqual({ x: 80, y: 90 });
    expect(removeRegionVertex(moved, 0, 2)[0]).toHaveLength(3);
    expect(
      removeRegionVertex(removeRegionVertex(moved, 0, 2), 0, 0)[0],
    ).toHaveLength(3);
  });

  it("creates insertion handles at the middle of every closing edge", () => {
    expect(regionEdgeHandles(square[0])).toEqual([
      { edgeIndex: 0, x: 50, y: 0 },
      { edgeIndex: 1, x: 100, y: 50 },
      { edgeIndex: 2, x: 50, y: 100 },
      { edgeIndex: 3, x: 0, y: 50 },
    ]);
  });
});
