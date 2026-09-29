import { afterEach, describe, expect, it } from "vitest";
import i18n, { setLocale } from "@/i18n";

describe("auth and campaign feature locales", () => {
  afterEach(async () => setLocale("pl"));

  it("resolves Polish feature messages at startup", () => {
    expect(i18n.global.t("auth.register.title")).toBe("Rejestracja");
    expect(i18n.global.t("campaignInvitations.title")).toBe("Moje zaproszenia");
    expect(i18n.global.t("vtt.table.settings.tabs.members")).toBe("Uczestnicy");
    expect(i18n.global.t("vtt.bestiary.title")).toBe("Bestiariusz bohatera");
    expect(i18n.global.t("vtt.bestiary.lockedTitle")).toBe(
      "Wpis pozostaje ukryty",
    );
    expect(i18n.global.t("vtt.table.compendium.bestiaryLevelSummary")).toBe(
      "Nazwa i krótki wstęp",
    );
    expect(i18n.global.t("vtt.table.compendium.bestiaryKnowledgeTab")).toBe(
      "Wiedza bohaterów",
    );
    expect(i18n.global.t("vtt.table.compendium.sourceContent")).toBe(
      "Treść podręcznikowa",
    );
    expect(i18n.global.t("vtt.table.compendium.gmNotes")).toBe("Materiały MG");
  });

  it("loads English feature messages with the locale", async () => {
    await setLocale("en");

    expect(i18n.global.t("auth.register.title")).toBe("Create an account");
    expect(i18n.global.t("campaignInvitations.title")).toBe("My invitations");
    expect(i18n.global.t("vtt.table.settings.tabs.members")).toBe("Members");
    expect(i18n.global.t("vtt.bestiary.title")).toBe("Hero bestiary");
    expect(i18n.global.t("vtt.bestiary.lockedTitle")).toBe(
      "Entry remains hidden",
    );
    expect(i18n.global.t("vtt.table.compendium.bestiaryLevelSummary")).toBe(
      "Name and short introduction",
    );
    expect(i18n.global.t("vtt.table.compendium.bestiaryKnowledgeTab")).toBe(
      "Hero knowledge",
    );
    expect(i18n.global.t("vtt.table.compendium.sourceContent")).toBe(
      "Source material",
    );
    expect(i18n.global.t("vtt.table.compendium.gmNotes")).toBe("GM materials");
  });
});
