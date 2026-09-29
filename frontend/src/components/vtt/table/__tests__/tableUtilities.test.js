import { describe, expect, it } from "vitest";

import {
  IMPLEMENTED_TABLE_UTILITIES,
  TABLE_UTILITIES,
  utilityById,
} from "../tableUtilities";
import { playerHudModalById } from "../playerHudModalRegistry";

describe("table utilities", () => {
  it("keeps one stable configuration entry for every required panel", () => {
    expect(TABLE_UTILITIES.map(({ id }) => id)).toEqual([
      "chat",
      "combat",
      "graphics",
      "characters",
      "professions",
      "items",
      "journal",
      "calendar",
      "handouts",
      "scenario",
      "scenes",
      "token-sync",
      "token-templates",
      "tables",
      "shop",
      "voice",
      "jukebox",
      "sound-effects",
      "compendium",
      "notifications",
      "settings",
    ]);
    expect(new Set(TABLE_UTILITIES.map(({ id }) => id)).size).toBe(21);
    expect(
      TABLE_UTILITIES.every(({ icon, labelKey }) => icon && labelKey),
    ).toBe(true);
  });

  it("marks only integrated panels as available", () => {
    expect(IMPLEMENTED_TABLE_UTILITIES).toEqual([
      "chat",
      "combat",
      "graphics",
      "characters",
      "professions",
      "calendar",
      "handouts",
      "compendium",
      "scenario",
      "scenes",
      "token-templates",
      "shop",
      "voice",
      "jukebox",
      "sound-effects",
      "notifications",
      "settings",
    ]);
  });

  it("opens token synchronization in a resizable GM workspace", () => {
    expect(utilityById("token-sync")).toMatchObject({
      icon: "chain",
      resizable: true,
      windowWidth: 980,
      minWindowWidth: 720,
      minWindowHeight: 520,
      windowOnly: true,
    });
  });

  it("opens the hero journal in its dedicated constrained window", () => {
    expect(utilityById("journal")).toMatchObject({
      windowType: "journal",
      windowWidth: 1180,
      windowHeight: 720,
      minWindowWidth: 760,
      minWindowHeight: 520,
      maxViewportWidthRatio: 0.95,
      maxViewportHeightRatio: 0.9,
      resizable: true,
      windowOnly: true,
    });
  });

  it("routes the hero bestiary to its central HUD modal", () => {
    expect(utilityById("bestiary")).toBeNull();
    expect(playerHudModalById("bestiary")).toMatchObject({
      content: "bestiary",
      width: 1180,
      height: 760,
    });
    expect(IMPLEMENTED_TABLE_UTILITIES).not.toContain("bestiary");
  });

  it("routes the spellbook to its central HUD modal", () => {
    expect(utilityById("spells")).toBeNull();
    expect(playerHudModalById("spells")).toMatchObject({
      content: "spells",
      width: 1120,
      height: 700,
    });
  });

  it("opens the calendar directly in one constrained resizable window", () => {
    expect(utilityById("calendar")).toMatchObject({
      icon: "calendar",
      windowType: "calendar",
      windowWidth: 1050,
      windowHeight: 720,
      minWindowWidth: 760,
      minWindowHeight: 520,
      maxViewportWidthRatio: 0.96,
      maxViewportHeightRatio: 0.94,
      directWindow: true,
      maximizable: true,
      resizable: true,
      constrainToViewport: true,
    });
  });

  it("keeps voice and jukebox window-capable while settings stays a drawer", () => {
    expect(utilityById("voice")).toMatchObject({
      icon: "microphone",
    });
    expect(utilityById("voice")?.windowWidth).toBeUndefined();
    expect(utilityById("jukebox")?.windowWidth).toBeUndefined();
    expect(utilityById("settings")?.drawerOnly).toBe(true);
  });

  it("places the resizable sound effects module beside the unchanged jukebox", () => {
    const ids = TABLE_UTILITIES.map(({ id }) => id);
    expect(ids.indexOf("sound-effects")).toBe(ids.indexOf("jukebox") + 1);
    expect(utilityById("jukebox")?.icon).toBe("music");
    expect(utilityById("sound-effects")).toMatchObject({
      icon: "audio-waveform",
      resizable: true,
      windowWidth: 1180,
      minWindowWidth: 860,
      maxWindowWidth: 1540,
    });
  });

  it("resolves only registered panels", () => {
    expect(utilityById("chat")).toMatchObject({
      labelKey: "vtt.table.rail.chat",
      windowType: "chat",
      windowWidth: 529,
      windowHeight: 530,
      aspectRatio: 529 / 530,
      centered: true,
      constrainToViewport: true,
    });
    expect(utilityById("unknown")).toBeNull();
  });

  it("opens character editing in a wide, high-density workspace", () => {
    expect(utilityById("characters")).toMatchObject({
      windowWidth: 1480,
      windowHeight: 900,
    });
  });

  it("opens professions in a shared resizable catalog window", () => {
    expect(utilityById("professions")).toMatchObject({
      icon: "book",
      windowType: "professions",
      windowWidth: 1120,
      windowHeight: 760,
      resizable: true,
      maximizable: true,
    });
  });

  it("opens handouts in a large floating workspace", () => {
    expect(utilityById("handouts")).toMatchObject({
      windowWidth: 1120,
      windowHeight: 780,
    });
  });

  it("opens the full compendium as a viewport-filling workspace", () => {
    expect(utilityById("compendium")).toMatchObject({
      windowWidth: 1480,
      windowHeight: 920,
      fillViewport: true,
    });
  });
});
