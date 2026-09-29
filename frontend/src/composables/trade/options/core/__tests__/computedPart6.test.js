import { describe, expect, it } from "vitest";
import { createCoreComputedPart6 } from "@/composables/trade/options/core/computedPart6";

const computed = createCoreComputedPart6({
  OWNER_CODES: { BG1: "BG1" },
});

describe("shop active player character", () => {
  it("uses the character selected for this shop context instead of the first GM owner", () => {
    const context = {
      isGM: false,
      context: { ownerCode: "CHAR_23", characterId: 23 },
      permissions: {
        isGm: true,
        ownerCodes: ["BG1", "BG2"],
      },
      actorOwnerCodes: ["BG1", "BG2", "CHAR_23"],
    };

    expect(computed.activeBgOwner.call(context)).toBe("CHAR_23");
  });

  it("falls back to the permitted owner before bootstrap provides a context", () => {
    const context = {
      isGM: false,
      context: null,
      permissions: { ownerCodes: ["BG2"] },
      actorOwnerCodes: ["BG1", "BG2"],
    };

    expect(computed.activeBgOwner.call(context)).toBe("BG2");
  });
});
