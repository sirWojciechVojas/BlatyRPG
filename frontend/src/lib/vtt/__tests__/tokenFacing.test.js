import { describe, expect, it } from "vitest";
import {
  normalizeTokenAngle,
  rotateTokenFacing,
  tokenFacingStyle,
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
});
