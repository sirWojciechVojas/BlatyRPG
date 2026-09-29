import { describe, expect, it } from "vitest";
import {
  movementPercent,
  tokenInitials,
  tokenMovementColor,
} from "../combatPresentation";

describe("combat tracker presentation", () => {
  it("clamps the movement meter to its valid visual range", () => {
    expect(movementPercent(3, 6)).toBe(50);
    expect(movementPercent(8, 6)).toBe(100);
    expect(movementPercent(-2, 6)).toBe(0);
    expect(movementPercent(2, 0)).toBe(0);
  });

  it("uses compact token initials when artwork is missing", () => {
    expect(tokenInitials("Jürgen Baer")).toBe("JB");
    expect(tokenInitials(" ")).toBe("?");
  });

  it("uses the color of the resource linked to movement", () => {
    const token = {
      resources: {
        bars: [
          { enabled: true, color: "#d95d55", movementSource: false },
          { enabled: true, color: "#609ee1", movementSource: true },
        ],
      },
    };

    expect(tokenMovementColor(token)).toBe("#609ee1");
    expect(tokenMovementColor({})).toBe("#4caf72");
  });
});
