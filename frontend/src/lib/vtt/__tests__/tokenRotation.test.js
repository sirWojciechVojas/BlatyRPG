import { describe, expect, it } from "vitest";
import { tokenAnglePreview, tokenPointerAngle } from "@/lib/vtt/tokenRotation";

describe("token rotation handles", () => {
  it.each([
    [{ clientX: 100, clientY: 0 }, 0],
    [{ clientX: 200, clientY: 100 }, 90],
    [{ clientX: 100, clientY: 200 }, 180],
    [{ clientX: 0, clientY: 100 }, 270],
  ])("maps pointer position to tabletop angle", (pointer, angle) => {
    expect(tokenPointerAngle({ x: 100, y: 100 }, pointer)).toBe(angle);
  });

  it("snaps pointer rotation to whole degrees", () => {
    expect(
      tokenPointerAngle({ x: 0, y: 0 }, { clientX: 100, clientY: 1 }),
    ).toBe(91);
  });

  it("previews facing independently from artwork rotation", () => {
    expect(
      tokenAnglePreview({ rotation: 30, facing: 60 }, { facing: 95 }),
    ).toMatchObject({
      rotation: 30,
      facing: 95,
    });
  });
});
