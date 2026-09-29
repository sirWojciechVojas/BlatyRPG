import { describe, expect, it } from "vitest";
import { wallsToPolygons } from "../regionGeometry";
import {
  applyWindowTransmission,
  effectiveDarknessAt,
  globalIlluminationAt,
} from "../scenePerception";
import { WALL_PRESETS, wallPreset } from "../wallPresets";

describe("advanced scene perception", () => {
  it("keeps wall capabilities independent in the built-in presets", () => {
    expect(WALL_PRESETS.solid).toMatchObject({
      blocksMovement: true,
      blocksSight: true,
      blocksLight: true,
      blocksSound: true,
    });
    expect(WALL_PRESETS.invisible).toMatchObject({
      blocksMovement: true,
      blocksSight: false,
      blocksLight: false,
      blocksSound: false,
    });
    expect(WALL_PRESETS.ethereal).toMatchObject({
      blocksMovement: false,
      blocksSight: true,
      blocksLight: true,
      blocksSound: false,
    });
    expect(WALL_PRESETS.terrain.restrictionType).toBe("limited");
    expect(wallPreset("secret")).toMatchObject({
      doorType: "secret",
      doorState: "closed",
    });
  });

  it("lets vision penetrate farther through a window at short distance", () => {
    const closeTransmission = applyWindowTransmission(2, 10, 100);
    const distantTransmission = applyWindowTransmission(20, 10, 100);
    expect(closeTransmission).toBeGreaterThan(distantTransmission);
    expect(closeTransmission).toBeLessThan(100);
  });

  it("applies ordered region darkness and local global-illumination overrides", () => {
    const scene = {
      darknessLevel: 0.3,
      globalIllumination: true,
      globalIlluminationThreshold: 0.5,
    };
    const polygon = [
      { x: 0, y: 0 },
      { x: 100, y: 0 },
      { x: 100, y: 100 },
      { x: 0, y: 100 },
    ];
    const regions = [
      {
        enabled: true,
        polygons: [polygon],
        darknessMode: "add",
        darknessValue: 0.4,
        disableGlobalIllumination: true,
      },
    ];
    expect(effectiveDarknessAt(scene, regions, { x: 50, y: 50 })).toBeCloseTo(
      0.7,
    );
    expect(globalIlluminationAt(scene, regions, { x: 50, y: 50 })).toBe(false);
    expect(globalIlluminationAt(scene, regions, { x: 150, y: 50 })).toBe(true);
  });

  it("builds a region only from a closed wall outline and reports a gap", () => {
    const square = [
      { x1: 0, y1: 0, x2: 100, y2: 0 },
      { x1: 100, y1: 0, x2: 100, y2: 100 },
      { x1: 100, y1: 100, x2: 0, y2: 100 },
      { x1: 0, y1: 100, x2: 0, y2: 0 },
    ];
    expect(wallsToPolygons(square)).toMatchObject({
      closed: true,
      gap: null,
    });
    expect(wallsToPolygons(square.slice(0, 3))).toMatchObject({
      closed: false,
      gap: { from: { x: 0, y: 100 }, to: { x: 0, y: 0 } },
    });
  });
});
