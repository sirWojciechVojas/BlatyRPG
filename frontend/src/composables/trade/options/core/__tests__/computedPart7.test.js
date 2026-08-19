import { describe, expect, it } from "vitest";
import { createCoreComputedPart7 } from "@/composables/trade/options/core/computedPart7";

describe("shop character portrait", () => {
  it("uses portrait instead of legacy avatar and token", () => {
    const computed = createCoreComputedPart7();
    const context = {
      activeBgOwner: "CHAR_23",
      actorByOwnerCode: {
        CHAR_23: {
          name: "Tel Aes In",
          avatar: "Telaesin_g6hfpk",
          assets: {
            avatar: { publicId: "character-assets/000001/avatar" },
            portrait: { publicId: "character-assets/000001/portrait" },
            token: { publicId: "character-assets/000001/token" },
          },
        },
      },
    };

    expect(computed.activeBgProfile.call(context).avatar).toBe(
      "https://res.cloudinary.com/dajzxmjyc/image/upload/f_auto,q_auto,c_fill,g_auto,w_512,h_768/character-assets/000001/portrait",
    );
  });
});
