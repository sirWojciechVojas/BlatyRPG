import { describe, expect, it } from "vitest";
import {
  normalizeSoundEffectScreenLayout,
  soundEffectScreenCapacity,
  soundEffectScreenGridStyle,
  visibleSoundEffectSlotCount,
} from "../soundEffectScreenLayout";

describe("sound effect screen layout", () => {
  it("uses the persisted format to calculate screen capacity", () => {
    const screen = {
      columns: 20,
      rows: 10,
      textLines: 5,
      padStyle: "wide",
    };

    expect(normalizeSoundEffectScreenLayout(screen)).toEqual(screen);
    expect(soundEffectScreenCapacity(screen)).toBe(200);
    expect(soundEffectScreenGridStyle(screen)).toEqual({
      gridTemplateColumns: "repeat(20, minmax(0, 1fr))",
      gridTemplateRows: "repeat(10, minmax(0, 1fr))",
      "--sound-effect-text-lines": 5,
    });
  });

  it("falls back safely when a stored format is missing or invalid", () => {
    expect(
      normalizeSoundEffectScreenLayout({
        columns: 40,
        rows: 0,
        textLines: "invalid",
        padStyle: "circle",
      }),
    ).toEqual({ columns: 20, rows: 1, textLines: 1, padStyle: "square" });
    expect(soundEffectScreenCapacity({})).toBe(12);
  });

  it("keeps assignments outside a temporarily reduced visible grid", () => {
    const screen = {
      columns: 2,
      rows: 2,
      slots: [{ position: 0 }, { position: 3 }, { position: 11 }],
    };

    expect(visibleSoundEffectSlotCount(screen)).toBe(2);
    expect(screen.slots).toHaveLength(3);
  });
});
