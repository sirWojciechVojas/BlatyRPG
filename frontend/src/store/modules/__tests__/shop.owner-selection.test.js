import { beforeEach, describe, expect, it } from "vitest";
import { setShopAccessSession } from "@/lib/trade/shopAccessSession";
import { resolveOwnerCode } from "../shop/runtime";

describe("shop owner selection", () => {
  beforeEach(() => window.localStorage.clear());

  it("prefers the explicitly selected HUD character over a stored development character", () => {
    setShopAccessSession({
      mode: "player",
      ownerCode: "BG1",
      characterId: 1,
    });

    expect(resolveOwnerCode({}, "hero_9")).toBe("HERO_9");
    expect(resolveOwnerCode({})).toBe("BG1");
  });

  it("prefers the authorized bootstrap context over a stale development character", () => {
    setShopAccessSession({
      mode: "player",
      ownerCode: "BG1",
      characterId: 1,
    });

    expect(
      resolveOwnerCode({
        context: { ownerCode: "char_23", characterId: 23 },
        permissions: { isGm: true, ownerCodes: ["BG1", "CHAR_23"] },
      }),
    ).toBe("CHAR_23");
  });
});
