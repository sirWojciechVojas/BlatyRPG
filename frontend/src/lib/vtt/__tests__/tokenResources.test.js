import { describe, expect, it } from "vitest";
import {
  activeTokenResources,
  normalizeTokenResources,
  numericActorAttributes,
  tokenBarPercent,
} from "@/lib/vtt/tokenResources";

describe("tokenResources", () => {
  it("always exposes four bars and three bubbles", () => {
    const resources = normalizeTokenResources({
      bars: [{ enabled: true, value: 6, max: 10 }],
    });
    expect(resources.bars).toHaveLength(4);
    expect(resources.bubbles).toHaveLength(3);
    expect(activeTokenResources(resources).bars).toHaveLength(1);
    expect(tokenBarPercent(resources.bars[0])).toBe(60);
    expect(
      normalizeTokenResources({
        bubbles: [{ attributePath: "__proto__.hp" }],
      }).bubbles[0].attributePath,
    ).toBe("");
  });

  it("lists only safe numeric actor leaves", () => {
    expect(
      numericActorAttributes({
        attributes: { actual: { hp: 8, name: "Guard" } },
        items: [{ quantity: 2 }],
      }),
    ).toEqual([{ path: "attributes.actual.hp", value: 8 }]);
  });
});
