import { describe, expect, it } from "vitest";
import {
  effectiveLight,
  lumenRangeScale,
  lumenStrength,
  normalizedLumens,
} from "../lightPhotometry";

describe("light photometry", () => {
  it("maps legacy intensity to lumen defaults", () => {
    expect(normalizedLumens({ intensity: 0.5 })).toBe(400);
    expect(normalizedLumens({ intensity: 1 })).toBe(800);
  });

  it("uses lumen power for both range and brightness", () => {
    expect(lumenRangeScale({ lumens: 3200 })).toBe(2);
    expect(lumenStrength({ lumens: 200 })).toBe(0.5);
    expect(
      effectiveLight({
        lumens: 3200,
        brightRadius: 100,
        dimRadius: 200,
        areaWidth: 300,
        areaHeight: 150,
      }),
    ).toMatchObject({
      brightRadius: 200,
      dimRadius: 400,
      areaWidth: 600,
      areaHeight: 300,
    });
  });
});
