import { describe, expect, it } from "vitest";
import {
  emptySceneDraft,
  firstInvalidSection,
  isSafeSceneAssetUrl,
  sceneDraftChanges,
  sceneDraftFingerprint,
  sceneDraftFrom,
  sceneDraftPayload,
  validateSceneDraft,
} from "../sceneSettingsModel";

describe("scene settings model", () => {
  it("copies only fields supported by the scene payload", () => {
    const draft = sceneDraftFrom({
      id: 9,
      revision: 4,
      name: "Ruins",
      gridOffsetX: 12,
    });

    expect(sceneDraftPayload(draft)).toMatchObject({
      name: "Ruins",
      gridOffsetX: 12,
    });
    expect(sceneDraftPayload(draft)).not.toHaveProperty("id");
    expect(sceneDraftPayload(draft)).not.toHaveProperty("revision");
  });

  it("marks a draft dirty only when a persisted value really differs", () => {
    const original = emptySceneDraft();
    const restored = sceneDraftFrom(original);
    expect(sceneDraftFingerprint(restored)).toBe(
      sceneDraftFingerprint(original),
    );

    restored.gridOpacity = 0.4;
    expect(sceneDraftFingerprint(restored)).not.toBe(
      sceneDraftFingerprint(original),
    );
  });

  it("builds a partial update from fields changed against the baseline", () => {
    const baseline = sceneDraftFrom({
      name: "Ruins",
      gridSize: 100,
      gridOpacity: 0.35,
    });
    const draft = {
      ...baseline,
      gridSize: 120,
      gridOffsetX: 8,
    };

    expect(sceneDraftChanges(draft, baseline)).toEqual({
      gridSize: 120,
      gridOffsetX: 8,
    });
  });

  it("mirrors API ranges and routes errors to the relevant section", () => {
    const draft = emptySceneDraft();
    draft.width = 120;
    draft.fogEdgeSoftness = 220;
    const errors = validateSceneDraft(draft);

    expect(errors).toHaveProperty("name");
    expect(errors).toHaveProperty("width");
    expect(errors).toHaveProperty("fogEdgeSoftness");
    expect(
      firstInvalidSection({ fogEdgeSoftness: errors.fogEdgeSoftness }),
    ).toBe("fog");
  });

  it("accepts protected relative assets and rejects unsafe URLs", () => {
    expect(
      isSafeSceneAssetUrl(
        "/api/campaigns/7/scene-assets/0123456789abcdef.webp/file",
      ),
    ).toBe(true);
    expect(isSafeSceneAssetUrl("javascript:alert(1)")).toBe(false);
    expect(isSafeSceneAssetUrl("https://example.test/map\u0000.webp")).toBe(
      false,
    );
    expect(firstInvalidSection()).toBeNull();
  });
});
