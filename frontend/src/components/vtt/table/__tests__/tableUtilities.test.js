import { describe, expect, it } from "vitest";

import {
  IMPLEMENTED_TABLE_UTILITIES,
  TABLE_UTILITIES,
  utilityById,
} from "../tableUtilities";

describe("table utilities", () => {
  it("keeps one stable compact rail entry for every required panel", () => {
    expect(TABLE_UTILITIES.map(({ id }) => id)).toEqual([
      "chat",
      "combat",
      "graphics",
      "characters",
      "items",
      "handouts",
      "scenario",
      "scenes",
      "tables",
      "shop",
      "jukebox",
      "compendium",
      "notifications",
      "settings",
    ]);
    expect(new Set(TABLE_UTILITIES.map(({ id }) => id)).size).toBe(14);
    expect(
      TABLE_UTILITIES.every(({ icon, labelKey }) => icon && labelKey),
    ).toBe(true);
  });

  it("marks only integrated panels as available", () => {
    expect(IMPLEMENTED_TABLE_UTILITIES).toEqual([
      "chat",
      "graphics",
      "characters",
      "scenario",
      "scenes",
      "shop",
      "notifications",
      "settings",
    ]);
  });

  it("resolves only registered panels", () => {
    expect(utilityById("chat")?.labelKey).toBe("vtt.table.rail.chat");
    expect(utilityById("unknown")).toBeNull();
  });
});
