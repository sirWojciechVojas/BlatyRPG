import { describe, expect, it } from "vitest";
import {
  activeTokenResources,
  normalizeTokenResources,
  numericActorAttributes,
  tokenBarPercent,
  tokenDisplayResourceBars,
  tokenMovementResourceState,
  updateLinkedTokenBubble,
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
      {
        enabled: true,
        label: "PR",
        color: "#4caf72",
        movementSource: true,
      },
    ]);
    expect(
      tokenDisplayResourceBars({
        resources,
        movementRange: 8,
        movementSpent: 2.5,
      })[1],
    ).toMatchObject({ label: "PR", value: 5.5, max: 8 });
  });

  it("uses a linked bubble as movement bar input", () => {
    const resources = normalizeTokenResources({
      bars: [undefined, { value: 6, max: 8, movementSource: true }],
      bubbles: [{ enabled: true, value: 3, linkedBarIndex: 1 }],
    });
    resources.bubbles[0].value = 3;
    const linked = updateLinkedTokenBubble(resources, 0);

    expect(linked.bars[1].value).toBe(3);
    expect(tokenMovementResourceState(linked)).toEqual({
      index: 1,
      range: 8,
      remaining: 3,
      spent: 5,
    });
  });

  it("keeps the selected movement bar visible and unique", () => {
    const resources = normalizeTokenResources({
      bars: [
        { enabled: false, movementSource: true },
        { enabled: true, movementSource: true },
      ],
    });

    expect(resources.bars[0]).toMatchObject({
      enabled: true,
      movementSource: true,
    });
    expect(resources.bars[1].movementSource).toBe(false);
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
