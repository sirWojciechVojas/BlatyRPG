import { beforeEach, describe, expect, it } from "vitest";
import {
  getShopAccessSession,
  setShopAccessSession,
  shopAccessHeaders,
} from "@/lib/trade/shopAccessSession";

describe("shopAccessSession", () => {
  beforeEach(() => window.localStorage.clear());

  it("persists a selected player and builds development headers", () => {
    setShopAccessSession({
      mode: "player",
      ownerCode: "bg2",
      characterId: 7,
      name: "Ludwig",
      playerId: "",
      playerLabel: "",
    });

    expect(getShopAccessSession()).toEqual({
      mode: "player",
      ownerCode: "BG2",
      characterId: 7,
      name: "Ludwig",
      playerId: "",
      playerLabel: "",
    });
    expect(shopAccessHeaders()).toEqual({
      "X-Shop-Access-Mode": "player",
      "X-Shop-View-Mode": "character",
      "X-Shop-Owner-Code": "BG2",
      "X-Shop-Character-Id": "7",
    });
  });

  it("does not accept an incomplete player selection", () => {
    setShopAccessSession({ mode: "player" });
    expect(getShopAccessSession()).toBeNull();
    expect(shopAccessHeaders()).toEqual({});
  });
});
