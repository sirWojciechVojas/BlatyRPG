import { describe, expect, it } from "vitest";
import {
  TOKEN_RESOURCE_BAR_POSITIONS,
  normalizeTokenResourceBarPosition,
} from "@/lib/vtt/tokenResourcePosition";

describe("tokenResourcePosition", () => {
  it("supports the four placements shown in token settings", () => {
    expect(TOKEN_RESOURCE_BAR_POSITIONS).toEqual([
      "above",
      "top-overlap",
      "bottom-overlap",
      "below",
    ]);
  });

  it("falls back to bars below the token", () => {
    expect(normalizeTokenResourceBarPosition("outside")).toBe("below");
    expect(normalizeTokenResourceBarPosition()).toBe("below");
  });
});
