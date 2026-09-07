import { describe, expect, it } from "vitest";
import {
  connectedWallEndpoints,
  nearestWallEndpoint,
  resolveWallPoint,
  simplifyWallPath,
  splitWallForOpening,
  wallColor,
  wallLength,
  wallMidpoint,
  wallPoint,
} from "@/lib/vtt/wallGeometry";

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
    expect(
      wallPoint({ clientX: 139, clientY: 81 }, surface, scene, false),
    ).toEqual({ x: 258, y: 122 });
  });

  it("calculates segment length and midpoint", () => {
    const wall = { x1: 10, y1: 20, x2: 40, y2: 60 };
    expect(wallLength(wall)).toBe(50);
    expect(wallMidpoint(wall)).toEqual({ x: 25, y: 40 });
  });

  it("snaps and discovers connected endpoints", () => {
    const walls = [
      { id: 1, x1: 0, y1: 0, x2: 100, y2: 100 },
      { id: 2, x1: 100, y1: 100, x2: 200, y2: 100 },
    ];
    expect(nearestWallEndpoint({ x: 96, y: 103 }, walls, 6).wall.id).toBe(1);
    expect(connectedWallEndpoints(walls, { x: 100, y: 100 })).toHaveLength(2);
  });

  it("prioritizes an off-grid wall endpoint over grid snapping", () => {
    const event = { clientX: 139, clientY: 81 };
    const walls = [{ id: 7, x1: 263, y1: 127, x2: 400, y2: 200 }];
    const connected = resolveWallPoint(event, surface, scene, walls, {
      snapToGrid: true,
      connect: true,
      tolerance: 10,
    });
    expect(connected.point).toEqual({ x: 263, y: 127 });
    expect(connected.connection.wall.id).toBe(7);
    expect(
      resolveWallPoint(event, surface, scene, walls, {
        snapToGrid: true,
        connect: false,
        tolerance: 10,
      }).point,
    ).toEqual({ x: 250, y: 100 });
  });

  it("uses state colors unless a custom color is configured", () => {
    expect(wallColor({ type: "door", doorState: "open" })).toBe("#71C98B");
    expect(wallColor({ type: "door", doorState: "locked" })).toBe("#E35E54");
    expect(wallColor({ type: "secret", color: "#123456" })).toBe("#123456");
  });

  it("simplifies a drawn curve while preserving its turn", () => {
    const path = simplifyWallPath(
      [
        { x: 0, y: 0 },
        { x: 25, y: 1 },
        { x: 50, y: 0 },
        { x: 75, y: 25 },
        { x: 100, y: 50 },
      ],
      3,
    );
    expect(path).toEqual([
      { x: 0, y: 0 },
      { x: 50, y: 0 },
      { x: 100, y: 50 },
    ]);
  });

  it("splits an existing wall around a centered opening", () => {
    expect(
      splitWallForOpening(
        { x1: 0, y1: 0, x2: 100, y2: 0 },
        { x: 50, y: 10 },
        20,
      ),
    ).toEqual({
      before: { x1: 0, y1: 0, x2: 40, y2: 0 },
      opening: { x1: 40, y1: 0, x2: 60, y2: 0 },
      after: { x1: 60, y1: 0, x2: 100, y2: 0 },
    });
  });
});
