import { describe, expect, it } from "vitest";
import { lightDraftFromDrag, validLightDraft } from "../lightInteraction";

describe("light creation interaction", () => {
  it("uses drag distance as dim radius and half of it as bright radius", () => {
    expect(lightDraftFromDrag({ x: 10, y: 20 }, { x: 40, y: 60 })).toEqual({
      x: 10,
      y: 20,
      brightRadius: 25,
      dimRadius: 50,
    });
  });

  it("rejects an accidental click without a useful radius", () => {
    expect(
      validLightDraft(lightDraftFromDrag({ x: 5, y: 5 }, { x: 5, y: 5 })),
    ).toBe(false);
  });
});
