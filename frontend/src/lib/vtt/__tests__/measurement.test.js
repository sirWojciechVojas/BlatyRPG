import { describe, expect, it } from "vitest";
import {
  conePath,
  formatDistance,
  measuredDistance,
} from "@/lib/vtt/measurement";

describe("VTT measurements", () => {
  it("converts viewport pixels through zoom and scene grid units", () => {
    const distance = measuredDistance(
      { gridSize: 100, gridDistance: 5 },
      { x: 10, y: 20 },
      { x: 610, y: 820 },
      2,
    );
    expect(distance).toBe(25);
    expect(formatDistance(distance, "m")).toBe("25 m");
  });

  it("builds a closed 60 degree cone path", () => {
    const path = conePath({ x: 0, y: 0 }, { x: 100, y: 0 });
    expect(path).toContain("A 100 100");
    expect(path.endsWith("Z")).toBe(true);
  });
});
