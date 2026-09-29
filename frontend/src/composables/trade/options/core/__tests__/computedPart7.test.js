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
            avatar: { mediaAssetId: 1, url: "https://media.example/avatar" },
            portrait: {
              mediaAssetId: 2,
              url: "https://media.example/portrait",
            },
            token: { mediaAssetId: 3, url: "https://media.example/token" },
          },
        },
      },
    };

    expect(computed.activeBgProfile.call(context).avatar).toBe(
      "https://media.example/portrait",
    );
  });
});
