import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";
import {
  PLAYER_HUD_MODALS,
  isPlayerHudModalId,
  playerHudModalById,
} from "../playerHudModalRegistry";

describe("player HUD modal registry", () => {
  it("defines the central dimensions for every implemented HUD module", () => {
    expect(PLAYER_HUD_MODALS.character).toMatchObject({
      width: 1600,
      height: 842,
      content: "character",
    });
    expect(PLAYER_HUD_MODALS.combat).toMatchObject({
      width: 520,
      height: 760,
      content: "combat",
    });
    expect(PLAYER_HUD_MODALS.journal).toMatchObject({
      width: 1180,
      height: 720,
      content: "journal",
    });
    expect(PLAYER_HUD_MODALS.bestiary).toMatchObject({
      width: 1180,
      height: 760,
      content: "bestiary",
    });
    expect(PLAYER_HUD_MODALS.spells).toMatchObject({
      width: 1120,
      height: 700,
      content: "spells",
    });
    expect(PLAYER_HUD_MODALS.shop).toMatchObject({
      width: 1600,
      height: 820,
      content: "shop",
    });
    expect(PLAYER_HUD_MODALS.settings).toMatchObject({
      width: 920,
      height: 600,
      content: "settings",
      requiresManage: true,
    });
  });

  it("routes every visible future action to a localized placeholder", () => {
    const placeholderIds = [
      "advance",
      "history",
      "notes",
      "traits",
      "purse",
      "abilities",
    ];
    expect(placeholderIds.map((id) => playerHudModalById(id))).toEqual(
      placeholderIds.map((id) =>
        expect.objectContaining({
          id,
          width: 720,
          height: 420,
          placeholder: true,
        }),
      ),
    );
    expect(isPlayerHudModalId("dice")).toBe(false);
    expect(isPlayerHudModalId("map")).toBe(false);
  });

  it("provides close and placeholder copy in Polish and English", () => {
    for (const locale of ["pl", "en"]) {
      const messages = JSON.parse(
        readFileSync(
          resolve(process.cwd(), `src/i18n/locales/vtt/${locale}.json`),
          "utf8",
        ),
      );
      const modalMessages = messages.vtt.table.playerHud.modal;
      expect(modalMessages.close).toBeTruthy();
      expect(modalMessages.comingSoon).toContain("{module}");
    }
  });
});
