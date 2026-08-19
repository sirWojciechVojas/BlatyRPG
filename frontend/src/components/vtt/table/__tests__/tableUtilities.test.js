import { describe, expect, it } from "vitest";

import { TABLE_UTILITIES, utilityById } from "../tableUtilities";

describe("table utilities", () => {
  it("keeps one stable compact rail entry for every required panel", () => {
    expect(TABLE_UTILITIES.map(({ id }) => id)).toEqual([
      "chat",
      "graphics",
      "characters",
      "handouts",
      "scenario",
      "shop",
      "jukebox",
      "notifications",
      "settings",
    ]);
    expect(new Set(TABLE_UTILITIES.map(({ id }) => id)).size).toBe(9);
    expect(
      TABLE_UTILITIES.every(({ icon, labelKey }) => icon && labelKey),
    ).toBe(true);
  });

  it("resolves only registered panels", () => {
    expect(utilityById("chat")?.labelKey).toBe("vtt.table.rail.chat");
    expect(utilityById("unknown")).toBeNull();
  });
});
