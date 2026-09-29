import { describe, expect, it } from "vitest";
import {
  normalizeTokenAngle,
  rotateTokenFacing,
  tokenFacingChanges,
  tokenFacingStyle,
  tokenFacingWheelChanges,
} from "@/lib/vtt/tokenFacing";

describe("tokenFacing", () => {
  it("normalizes negative and overflowing angles", () => {
    expect(normalizeTokenAngle(-15)).toBe(345);
    expect(normalizeTokenAngle(375)).toBe(15);
  });

  it("rotates appearance and persisted facing together", () => {
    expect(rotateTokenFacing({ rotation: 350, facing: 350 }, 15)).toEqual({
      rotation: 5,
      facing: 5,
    });
  });

  it("uses rotation when a legacy token has no facing", () => {
    expect(tokenFacingStyle({ rotation: 90 })).toEqual({
      transform: "rotate(90deg)",
    });
  });

  it("rotates only facing when artwork linkage is disabled", () => {
    expect(
      tokenFacingWheelChanges(
        { rotation: 40, facing: 350, rotationFollowsFacing: false },
        100,
      ),
    ).toEqual({ facing: 5 });
  });

  it("preserves the artwork offset when facing and rotation are linked", () => {
    expect(
      tokenFacingChanges(
        { rotation: 20, facing: 350, rotationFollowsFacing: true },
        5,
      ),
    ).toEqual({ facing: 5, rotation: 35 });
  });

  it("rotates facing counter-clockwise when scrolling upward", () => {
    expect(tokenFacingWheelChanges({ rotation: 0, facing: 5 }, -1)).toEqual({
      facing: 350,
    });
  });
});
