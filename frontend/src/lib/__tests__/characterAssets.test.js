import { describe, expect, it } from "vitest";
import {
  buildCharacterAssetUrl,
  resolveCharacterAssetSource,
} from "@/lib/characterAssets";

describe("character assets", () => {
  it("builds an URL from a canonical Cloudinary public ID", () => {
    expect(
      buildCharacterAssetUrl("character-assets/000037/portrait", "portrait"),
    ).toBe(
      "https://res.cloudinary.com/dajzxmjyc/image/upload/f_auto,q_auto,c_fill,g_auto,w_512,h_768/character-assets/000037/portrait",
    );
  });

  it("resolves a typed asset map", () => {
    expect(
      resolveCharacterAssetSource(
        {
          assets: {
            token: { publicId: "character-assets/000037/token" },
          },
        },
        "token",
      ),
    ).toContain("/character-assets/000037/token");
  });
});
