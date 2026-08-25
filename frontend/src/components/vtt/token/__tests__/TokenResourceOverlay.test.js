import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it, vi } from "vitest";
import {
  activeTokenResources,
  cloneTokenResources,
  normalizeTokenResources,
  tokenBarPercent,
} from "@/lib/vtt/tokenResources";
import { tokenResourceBubbleOffsets } from "@/lib/vtt/tokenResourcePosition";

const loadComponent = () => {
  const path = resolve(
    process.cwd(),
    "src/components/vtt/token/TokenResourceOverlay.vue",
  );
  const { descriptor } = parse(readFileSync(path, "utf8"), { filename: path });
  const executable = descriptor.script.content
    .replace(/import \{[\s\S]*?\} from "@\/lib\/vtt\/tokenResources";/u, "")
    .replace(
      /import \{[\s\S]*?\} from "@\/lib\/vtt\/tokenResourcePosition";/u,
      "",
    )
    .replace("export default {", "return {");
  return new Function(
    "activeTokenResources",
    "cloneTokenResources",
    "normalizeTokenResources",
    "tokenBarPercent",
    "tokenResourceBubbleOffsets",
    executable,
  )(
    activeTokenResources,
    cloneTokenResources,
    normalizeTokenResources,
    tokenBarPercent,
    tokenResourceBubbleOffsets,
  );
};

describe("TokenResourceOverlay", () => {
  it("edits a bubble inline and emits the complete resource model", () => {
    const component = loadComponent();
    const emitted = vi.fn();
    const selected = vi.fn();
    const context = {
      editable: true,
      editingIndex: null,
      draftValue: "",
      resources: {
        bubbles: [
          { enabled: true, label: "Pancerz", value: 2, position: "top-left" },
        ],
      },
      $refs: { bubbleInput: { select: selected } },
      $nextTick: (callback) => callback(),
      $emit: emitted,
    };

    component.methods.startEdit.call(context, { index: 0, item: { value: 2 } });
    expect(context.editingIndex).toBe(0);
    expect(selected).toHaveBeenCalledOnce();

    context.draftValue = "7";
    component.methods.commitEdit.call(context);
    expect(emitted).toHaveBeenCalledWith(
      "update",
      expect.objectContaining({
        bubbles: expect.arrayContaining([
          expect.objectContaining({ value: 7 }),
        ]),
      }),
    );
  });

  it("keeps bubbles clear of bars rendered on the same side", () => {
    const component = loadComponent();
    const context = {
      barPosition: "below",
      resources: {
        bars: [
          { enabled: true, label: "HP" },
          { enabled: true, label: "PR" },
        ],
      },
    };

    expect(component.computed.overlayStyle.call(context)).toEqual({
      "--token-resource-bubble-top": "-42px",
      "--token-resource-bubble-bottom": "-93px",
    });
  });
});
