import { describe, expect, it } from "vitest";
import {
  pendingTokenPositionResolved,
  tokenDisplayPosition,
  tokenTravelDuration,
} from "../tokenMotion";

describe("token movement timing", () => {
  it("keeps short movement responsive and gives long travel more time", () => {
    expect(tokenTravelDuration({ x: 0, y: 0 }, { x: 20, y: 0 })).toBe(260);
    expect(tokenTravelDuration({ x: 0, y: 0 }, { x: 400, y: 0 })).toBe(480);
  });

  it("caps long-distance animation to keep the table responsive", () => {
    expect(tokenTravelDuration({ x: 0, y: 0 }, { x: 5000, y: 5000 })).toBe(720);
  });

  it("holds a dropped token until the authoritative position arrives", () => {
    const pending = { x: 300, y: 200, revision: 4 };

    expect(
      pendingTokenPositionResolved(pending, { x: 100, y: 100, revision: 4 }),
    ).toBe(false);
    expect(
      pendingTokenPositionResolved(pending, { x: 300, y: 200, revision: 5 }),
    ).toBe(true);
    expect(
      pendingTokenPositionResolved(pending, { x: 280, y: 200, revision: 5 }),
    ).toBe(true);
  });

  it("keeps the real token at its origin while only a drag preview exists", () => {
    const token = { x: 100, y: 200 };

    expect(tokenDisplayPosition(token)).toBe(token);
    expect(tokenDisplayPosition(token, { x: 300, y: 400 })).toEqual({
      x: 300,
      y: 400,
    });
  });
});
