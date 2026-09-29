import { describe, expect, it } from "vitest";
import {
  normalizeWallSoundConfig,
  pointAlongWall,
  wallSoundDistance,
  wallSoundGainAtDistance,
  wallSoundOffsetSegment,
  wallSoundZones,
} from "../wallSound";

const scene = { gridSize: 100, gridDistance: 5 };
const wall = { x1: 0, y1: 100, x2: 200, y2: 100, enabled: true };

describe("wall sound geometry", () => {
  it("normalizes legacy door cues without losing their URLs", () => {
    const config = normalizeWallSoundConfig({
      open: "/audio/door-open.ogg",
      close: "javascript:alert(1)",
      volume: 0.6,
    });
    expect(config).toMatchObject({ version: 2 });
    expect(config.rules).toHaveLength(1);
    expect(config.rules[0]).toMatchObject({
      id: "legacy-open",
      trigger: "open",
      legacyUrl: "/audio/door-open.ogg",
      volume: 0.6,
    });
  });

  it.each([1, 3, 12])("divides one total range into %i zones", (count) => {
    const zones = wallSoundZones({ range: 24, zoneCount: count, volume: 1 });
    expect(zones).toHaveLength(count);
    expect(zones[0].inner).toBe(0);
    expect(zones.at(-1).outer).toBe(24);
    expect(zones.at(-1).outerGain).toBe(0);
  });

  it("raises volume smoothly when a listener approaches", () => {
    const rule = { range: 12, zoneCount: 3, volume: 0.9 };
    const far = wallSoundGainAtDistance(rule, 10);
    const middle = wallSoundGainAtDistance(rule, 6);
    const near = wallSoundGainAtDistance(rule, 1);
    expect(near).toBeGreaterThan(middle);
    expect(middle).toBeGreaterThan(far);
    expect(wallSoundGainAtDistance(rule, 12)).toBe(0);
    expect(wallSoundGainAtDistance(rule, 13)).toBe(0);
  });

  it("places point emitters on the wall using normalized positions", () => {
    expect(pointAlongWall(wall, 0.25)).toEqual({ x: 50, y: 100 });
  });

  it("offsets the full-wall buffer in scene units", () => {
    expect(
      wallSoundOffsetSegment(wall, { mode: "offsetLine", offset: 5 }, scene),
    ).toMatchObject({ x1: 0, y1: 200, x2: 200, y2: 200 });
  });

  it("uses the nearest of multiple point emitters", () => {
    const rule = {
      range: 20,
      zoneCount: 3,
      geometry: { mode: "points", points: [0.25, 0.75] },
    };
    expect(wallSoundDistance(wall, rule, { x: 150, y: 200 }, scene)).toBe(5);
  });
});
