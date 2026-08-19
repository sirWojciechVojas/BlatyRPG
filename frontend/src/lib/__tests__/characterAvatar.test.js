import { describe, expect, it } from "vitest";
import {
  createCharacterInitialsAvatar,
  resolveCharacterAvatar,
  resolveCharacterPortrait,
  resolveCharacterToken,
} from "@/lib/trade/characterAvatar";

describe("character avatar resolver", () => {
  it("keeps a complete image URL", () => {
    expect(
      resolveCharacterAvatar("https://example.test/igor.png", "Igor"),
    ).toBe("https://example.test/igor.png");
  });

  it("builds the legacy Cloudinary image URL from the avatar field", () => {
    const avatar = resolveCharacterAvatar(
      "Igor_z_Emmanuelplatz_oghss4",
      "Igor z Emmanuelplatz",
    );

    expect(avatar).toBe(
      "https://res.cloudinary.com/dajzxmjyc/image/upload/f_auto,q_auto,c_fill,g_auto,w_128,h_128/Igor_z_Emmanuelplatz_oghss4",
    );
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
        { publicId: "character-assets/000037/portrait" },
        { publicId: "character-assets/000037/avatar" },
        "Igor",
      ),
    ).toBe(
      "https://res.cloudinary.com/dajzxmjyc/image/upload/f_auto,q_auto,c_fill,g_auto,w_512,h_768/character-assets/000037/portrait",
    );
  });

  it("resolves the token asset when explicitly requested", () => {
    expect(
      resolveCharacterToken(
        { publicId: "character-assets/000001/token" },
        { publicId: "character-assets/000001/portrait" },
        { publicId: "character-assets/000001/avatar" },
        "Tel Aes In",
      ),
    ).toBe(
      "https://res.cloudinary.com/dajzxmjyc/image/upload/f_auto,q_auto,c_fill,g_auto,w_256,h_256/character-assets/000001/token",
    );
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
});
