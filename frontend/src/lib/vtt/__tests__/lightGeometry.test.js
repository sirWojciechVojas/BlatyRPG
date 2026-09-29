import { describe, expect, it } from "vitest";
import {
  lightPolygonPath,
  lightPolygonPoints,
  lightIsActive,
  lightTransitionOffsets,
  lightTechnicalPath,
  tokenVisionSource,
  wallBlocksLight,
  wallBlocksSight,
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

  it("uses independent light and vision restrictions and ignores disabled walls", () => {
    const sightOnly = {
      ...wall,
      blocksLight: false,
      blocksSight: true,
      enabled: true,
    };
    const lightPoints = lightPolygonPoints(light, [sightOnly], scene, "light");
    const visionPoints = lightPolygonPoints(light, [sightOnly], scene, "sight");
    expect(wallBlocksLight(sightOnly)).toBe(false);
    expect(wallBlocksSight(sightOnly)).toBe(true);
    expect(
      lightPoints.find((point) => point.y === 100 && point.x >= 100).x,
    ).toBe(200);
    expect(
      visionPoints.find((point) => point.y === 100 && point.x >= 100).x,
    ).toBe(150);
    expect(wallBlocksSight({ ...sightOnly, enabled: false })).toBe(false);
  });

  it("builds a closed SVG path", () => {
    expect(lightPolygonPath(light, [wall], scene)).toMatch(/^M .+ Z$/);
  });

  it("limits directional and cone lights to the configured angle", () => {
    const points = lightPolygonPoints(
      { ...light, sourceType: "cone", direction: 0, angle: 60 },
      [],
      scene,
    );
    expect(points[0]).toEqual({ x: 100, y: 100 });
    expect(points.every((point) => point.x >= 100)).toBe(true);
  });

  it("renders a uniform area using its rectangular geometry", () => {
    const points = lightPolygonPoints(
      { ...light, sourceType: "area", areaWidth: 200, areaHeight: 100 },
      [],
      scene,
    );
    expect(Math.max(...points.map((point) => point.x))).toBe(200);
    expect(Math.min(...points.map((point) => point.x))).toBe(0);
    expect(Math.max(...points.map((point) => point.y))).toBe(150);
    expect(
      lightTechnicalPath({ ...light, sourceType: "area" }, [], scene),
    ).toMatch(/^M/);
  });

  it("does not clip a source configured to ignore walls", () => {
    const points = lightPolygonPoints(
      { ...light, constrainedByWalls: false },
      [wall],
      scene,
    );
    const east = points.find((point) => point.y === 100 && point.x >= 100);
    expect(east.x).toBe(200);
  });

  it("activates a source only inside its darkness range", () => {
    const ranged = { enabled: true, darknessMin: 0.4, darknessMax: 0.8 };
    expect(lightIsActive(ranged, 0.3)).toBe(false);
    expect(lightIsActive(ranged, 0.6)).toBe(true);
    expect(lightIsActive(ranged, 0.9)).toBe(false);
  });

  it("uses softness only for gradual illumination", () => {
    expect(
      lightTransitionOffsets({
        brightRadius: 50,
        dimRadius: 100,
        softness: 0.5,
      }),
    ).toEqual({ bright: 50, fade: 75 });
    expect(
      lightTransitionOffsets({
        brightRadius: 50,
        dimRadius: 100,
        softness: 0.5,
        gradualIllumination: false,
      }),
    ).toEqual({ bright: 50, fade: 100 });
  });

  it("builds wall-aware Vision only for a controlled enabled token", () => {
    const token = {
      id: 7,
      x: 100,
      y: 200,
      width: 80,
      height: 80,
      elevation: 2,
      vision: { enabled: true, range: 450 },
      capabilities: { canControl: true },
    };
    expect(tokenVisionSource(token, { gridSize: 80 })).toMatchObject({
      id: "token-7",
      x: 140,
      y: 240,
      dimRadius: 450,
      constrainedByWalls: true,
      elevation: 2,
    });
    expect(
      tokenVisionSource({ ...token, capabilities: { canControl: false } }),
    ).toBeNull();
  });
});
