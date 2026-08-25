import { describe, expect, it } from "vitest";
import {
  activeTokenResources,
  normalizeTokenResources,
  numericActorAttributes,
  tokenBarPercent,
  tokenDisplayResourceBars,
} from "@/lib/vtt/tokenResources";

describe("tokenResources", () => {
  it("always exposes four bars and three bubbles", () => {
    const resources = normalizeTokenResources({
      bars: [{ enabled: true, value: 6, max: 10 }],
    });
    expect(resources.bars).toHaveLength(4);
    expect(resources.bubbles).toHaveLength(3);
    expect(activeTokenResources(resources).bars).toHaveLength(2);
    expect(tokenBarPercent(resources.bars[0])).toBe(60);
    expect(
      normalizeTokenResources({
        bubbles: [{ attributePath: "__proto__.hp" }],
      }).bubbles[0].attributePath,
    ).toBe("");
  });

  it("defaults to red HP and green movement resource bars", () => {
    const resources = normalizeTokenResources();
    expect(resources.bars.slice(0, 2)).toMatchObject([
      { enabled: true, label: "HP", color: "#d95d55" },
      { enabled: true, label: "PR", color: "#4caf72" },
    ]);
    expect(
      tokenDisplayResourceBars({
        resources,
        movementRange: 8,
        movementSpent: 2.5,
      })[1],
    ).toMatchObject({ label: "PR", value: 5.5, max: 8 });
  });

  it("upgrades the previous empty bar defaults", () => {
    const resources = normalizeTokenResources({
      bars: ["#4caf72", "#d95d55", "#4f91d9", "#d5a64f"].map((color) => ({
        enabled: false,
        color,
        value: 0,
        max: 0,
      })),
    });

    expect(resources.bars.slice(0, 2)).toMatchObject([
      { enabled: true, label: "HP", color: "#d95d55" },
      { enabled: true, label: "PR", color: "#4caf72" },
    ]);
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
