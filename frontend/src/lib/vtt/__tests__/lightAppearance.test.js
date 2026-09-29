import { describe, expect, it } from "vitest";
import { lightTintWeight } from "../lightAppearance";

describe("light appearance", () => {
  it("keeps legacy lights clear instead of applying a white veil", () => {
    expect(lightTintWeight({}, 1)).toBeCloseTo(0.04);
    expect(lightTintWeight({}, 0)).toBeCloseTo(0.008);
  });

  it("uses bounded clarity for an intentional atmospheric accent", () => {
    expect(lightTintWeight({ clarity: 1 }, 1)).toBeCloseTo(0.18);
    expect(lightTintWeight({ clarity: 2 }, 1)).toBeCloseTo(0.18);
    expect(lightTintWeight({ clarity: -1 }, 1)).toBeCloseTo(0.04);
  });
});
