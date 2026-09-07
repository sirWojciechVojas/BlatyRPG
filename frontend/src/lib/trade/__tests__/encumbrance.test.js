import { describe, expect, it } from "vitest";
import {
  BG_CARRY_LIMIT,
  ENCUMBRANCE_STATUS,
  calculateInventoryEncumbrance,
  resolveEncumbranceStatus,
} from "@/lib/trade/encumbrance";

describe("shared inventory encumbrance", () => {
  it("uses the same charge times quantity rule as the player shop", () => {
    const items = [
      { CHARGE: 7, QUANTITY: 3 },
      { CHARGE: 4, QUANTITY: 1 },
      { INV_ID: 12, QUANTITY: 2 },
    ];
    const templates = { 12: { CHARGE: 5 } };

    expect(calculateInventoryEncumbrance(items, templates)).toBe(35);
  });

  it("keeps the shop thresholds and carrying limit", () => {
    expect(BG_CARRY_LIMIT).toBe(300);
    expect(resolveEncumbranceStatus(209)).toBe(ENCUMBRANCE_STATUS.LIGHT);
    expect(resolveEncumbranceStatus(210)).toBe(ENCUMBRANCE_STATUS.HEAVY);
    expect(resolveEncumbranceStatus(270)).toBe(ENCUMBRANCE_STATUS.WARNING);
    expect(resolveEncumbranceStatus(301)).toBe(ENCUMBRANCE_STATUS.OVERLOADED);
  });
});
