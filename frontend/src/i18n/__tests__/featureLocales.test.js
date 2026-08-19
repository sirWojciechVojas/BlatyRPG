import { afterEach, describe, expect, it } from "vitest";
import i18n, { setLocale } from "@/i18n";

describe("auth and campaign feature locales", () => {
  afterEach(async () => setLocale("pl"));

  it("resolves Polish feature messages at startup", () => {
    expect(i18n.global.t("auth.register.title")).toBe("Rejestracja");
    expect(i18n.global.t("campaignLobby.myInvitations.title")).toBe(
      "Moje zaproszenia",
    );
  });

  it("loads English feature messages with the locale", async () => {
    await setLocale("en");

    expect(i18n.global.t("auth.register.title")).toBe("Create an account");
    expect(i18n.global.t("campaignLobby.myInvitations.title")).toBe(
      "My invitations",
    );
  });
});
