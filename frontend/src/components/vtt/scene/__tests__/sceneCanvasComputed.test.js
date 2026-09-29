import { describe, expect, it } from "vitest";
import { sceneCanvasComputed } from "../sceneCanvasComputed";

const computedContext = ({
  canManageScene = true,
  fogPreview = { mode: "gm", id: null },
  selectedTokenId = null,
  selectedTokenIds = [],
  tokens = [],
} = {}) => {
  const context = {
    canManageScene,
    fogPreview,
    selectedTokenId,
    selectedTokenIds,
    tokens,
    tokenVisionPreviews: {},
    tokenVisionAnglePreviews: {},
  };
  context.fogTokens = sceneCanvasComputed.fogTokens.call(context);
  context.selectedVisionToken =
    sceneCanvasComputed.selectedVisionToken.call(context);
  return context;
};

describe("scene canvas fog preview", () => {
  it("keeps the selected token available to its HUD outside vision", () => {
    const context = {
      scene: { fogEnabled: true },
      canManageScene: true,
      fogVisibility: { constrained: true, visibleTokenIds: [8] },
      selectedTokenId: 7,
      selectedTokenIds: [7],
      tokens: [
        { id: 7, capabilities: { canControl: true } },
        { id: 8, capabilities: { canControl: true } },
        { id: 9, capabilities: { canControl: true } },
      ],
    };

    expect(
      sceneCanvasComputed.displayTokens.call(context).map((token) => token.id),
    ).toEqual([7, 8]);
  });

  it("raises token UI above constrained vision outside the fog editor", () => {
    expect(
      sceneCanvasComputed.tokenUiAboveFog.call({
        activeTool: "select",
        fogVisibility: { constrained: true },
        selectedTokenId: 7,
        selectedTokenIds: [7],
      }),
    ).toBe(true);
    expect(
      sceneCanvasComputed.tokenUiAboveFog.call({
        activeTool: "fog",
        fogVisibility: { constrained: true },
        selectedTokenId: 7,
        selectedTokenIds: [7],
      }),
    ).toBe(false);
  });

  it("uses the selected vision-enabled token as the GM fog preview", () => {
    const context = computedContext({
      selectedTokenId: 7,
      selectedTokenIds: [7],
      tokens: [
        { id: 7, vision: { enabled: true } },
        { id: 8, vision: { enabled: true } },
      ],
    });

    expect(sceneCanvasComputed.effectiveFogPreview.call(context)).toEqual({
      mode: "token",
      id: 7,
    });
  });

  it("keeps the full GM view when the selected token has vision disabled", () => {
    const context = computedContext({
      selectedTokenId: 7,
      selectedTokenIds: [7],
      tokens: [{ id: 7, vision: { enabled: false } }],
    });

    expect(sceneCanvasComputed.effectiveFogPreview.call(context)).toEqual({
      mode: "gm",
      id: null,
    });
  });

  it("does not override an explicit player fog preview", () => {
    const context = computedContext({
      fogPreview: { mode: "user", id: 12 },
      selectedTokenId: 7,
      selectedTokenIds: [7],
      tokens: [{ id: 7, vision: { enabled: true } }],
    });

    expect(sceneCanvasComputed.effectiveFogPreview.call(context)).toEqual({
      mode: "user",
      id: 12,
    });
  });
});
