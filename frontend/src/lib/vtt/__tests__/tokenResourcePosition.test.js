import { describe, expect, it } from "vitest";
import {
  TOKEN_RESOURCE_BAR_POSITIONS,
  normalizeTokenResourceBarPosition,
  tokenResourceBubbleOffsets,
  tokenResourceStackHeight,
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

  it("moves bubbles beyond a resource stack on the same side", () => {
    expect(tokenResourceStackHeight(2)).toBe(28);
    expect(tokenResourceBubbleOffsets("above", 2)).toEqual({
      top: 77,
      bottom: 66,
    });
    expect(tokenResourceBubbleOffsets("top-overlap", 2).top).toBe(56);
    expect(tokenResourceBubbleOffsets("bottom-overlap", 2).bottom).toBe(74);
    expect(tokenResourceBubbleOffsets("below", 2)).toEqual({
      top: 42,
      bottom: 93,
    });
  });
});
