import { describe, expect, it } from "vitest";
import {
  createCharacterInitialsAvatar,
  resolveCharacterAvatar,
  resolveCharacterPortrait,
  resolveCharacterToken,
  resolveCharacterTokenSource,
} from "@/lib/trade/characterAvatar";

describe("character avatar resolver", () => {
  it("keeps a complete image URL", () => {
    expect(
      resolveCharacterAvatar("https://example.test/igor.png", "Igor"),
    ).toBe("https://example.test/igor.png");
  });

  it("does not expose provider-specific legacy IDs", () => {
    const avatar = resolveCharacterAvatar(
      "Igor_z_Emmanuelplatz_oghss4",
      "Igor z Emmanuelplatz",
    );

    expect(avatar).toBe(createCharacterInitialsAvatar("Igor z Emmanuelplatz"));
  });

  it("prefers the avatar returned in an asset set", () => {
    expect(
      resolveCharacterAvatar(
        {
          publicId: "character-assets/000037/avatar",
          url: "https://cdn.example.test/avatar",
        },
        "Igor",
      ),
    ).toBe("https://cdn.example.test/avatar");
  });

  it("uses the portrait asset in the shop profile", () => {
    expect(
      resolveCharacterPortrait(
        { mediaAssetId: 37, url: "https://cdn.example.test/portrait" },
        { mediaAssetId: 38, url: "https://cdn.example.test/avatar" },
        "Igor",
      ),
    ).toBe("https://cdn.example.test/portrait");
  });

  it("resolves the token asset when explicitly requested", () => {
    expect(
      resolveCharacterToken(
        { mediaAssetId: 1, url: "https://cdn.example.test/token" },
        { mediaAssetId: 2, url: "https://cdn.example.test/portrait" },
        { mediaAssetId: 3, url: "https://cdn.example.test/avatar" },
        "Tel Aes In",
      ),
    ).toBe("https://cdn.example.test/token");
  });

  it("falls back to avatar when the portrait is unavailable", () => {
    expect(
      resolveCharacterPortrait(
        "",
        { url: "https://cdn.example.test/avatar" },
        "Igor",
      ),
    ).toBe("https://cdn.example.test/avatar");
  });

  it("creates a character-specific fallback when avatar is empty", () => {
    const avatar = resolveCharacterAvatar("", "Igor z Emmanuelplatz");

    expect(avatar).toBe(createCharacterInitialsAvatar("Igor z Emmanuelplatz"));
    expect(decodeURIComponent(avatar)).toContain(">IE<");
  });

  it("keeps presentation fallbacks out of persisted token data", () => {
    const character = {
      id: 37,
      name: "Bez Avatara",
      assets: {},
      avatarUrl: "",
    };

    expect(resolveCharacterAvatar(character, character.name)).toMatch(
      /^data:image\/svg\+xml,/u,
    );
    expect(resolveCharacterTokenSource(character, character, character)).toBe(
      "",
    );
  });
});
