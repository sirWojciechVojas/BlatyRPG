import { describe, expect, it } from "vitest";
import {
  addAssetObject,
  applyAiProposal,
  createCommandHistory,
  createMapDocument,
  publicMapDocument,
  snapPoint,
  validateMapDocument,
} from "../mapDocument";
import { STARTER_ASSET_COUNT, searchStarterAssets } from "../starterAssets";

describe("map document", () => {
  it("ships a searchable 24-element starter package", () => {
    expect(STARTER_ASSET_COUNT).toBe(24);
    expect(searchStarterAssets("karczma").length).toBeGreaterThan(1);
    expect(searchStarterAssets("kamień").length).toBeGreaterThan(0);
  });

  it("uses the same snapped pixel coordinates as VTT geometry", () => {
    const document = createMapDocument({ gridSize: 100 });
    expect(snapPoint(document, { x: 146, y: 252 })).toEqual({ x: 100, y: 300 });
    const object = addAssetObject(document, "starter.door", { x: 100, y: 200 });
    expect(object.x).toBe(100);
    expect(object.y).toBe(200);
    const torch = addAssetObject(document, "starter.torch", { x: 200, y: 200 });
    expect(torch.light.brightRadius).toBe(250);
    expect(torch.light.dimRadius).toBe(600);
  });

  it("undoes an accepted AI proposal as one command and respects locks", () => {
    const base = createMapDocument();
    base.objects.push({
      ...addAssetObject(base, "starter.bed", { x: 400, y: 400 }),
      id: "locked-bed",
      locked: true,
    });
    const history = createCommandHistory(base);
    history.execute("AI", (draft) =>
      applyAiProposal(draft, {
        removeObjectIds: ["locked-bed"],
        objects: [
          addAssetObject(draft, "starter.tavern-table", { x: 800, y: 800 }),
        ],
      }),
    );
    expect(history.document.objects).toHaveLength(2);
    expect(history.undo().objects).toHaveLength(1);
  });

  it("rejects AI writes to a locked layer", () => {
    const document = createMapDocument();
    document.layers.find((layer) => layer.id === "objects").locked = true;
    expect(() =>
      applyAiProposal(document, {
        removeObjectIds: [],
        objects: [
          addAssetObject(document, "starter.crate", { x: 300, y: 300 }),
        ],
      }),
    ).toThrow("ai_locked_layer:objects");
  });

  it("filters GM-only objects from publication", () => {
    const document = createMapDocument();
    document.layers.push({
      id: "gm-only",
      name: "Sekrety MG",
      order: 99,
      visible: true,
      private: true,
    });
    document.objects.push(
      {
        ...addAssetObject(document, "starter.crate", { x: 100, y: 100 }),
        private: true,
      },
      addAssetObject(document, "starter.barrel", { x: 200, y: 100 }),
      {
        ...addAssetObject(document, "starter.chair", { x: 300, y: 100 }),
        layerId: "gm-only",
      },
    );
    const published = publicMapDocument(document);
    expect(published.objects).toHaveLength(1);
    expect(published.layers.some((layer) => layer.id === "gm-only")).toBe(
      false,
    );
    expect(validateMapDocument(document).valid).toBe(true);
  });

  it("keeps versioned custom asset metadata outside Vue-specific state", () => {
    const document = createMapDocument();
    const custom = addAssetObject(
      document,
      "custom.inn-sign",
      { x: 500, y: 400 },
      {
        asset: {
          id: "custom.inn-sign",
          version: 3,
          kind: "sprite",
          source: { url: "/api/campaigns/7/maps/assets/42/file" },
          physicalSize: { width: 1.5, height: 2, unit: "m" },
          anchor: { x: 0.5, y: 0.9 },
        },
      },
    );
    expect(custom.assetVersion).toBe(3);
    expect(custom.width).toBe(150);
    expect(custom.assetUrl).toContain("/maps/assets/42/file");
  });

  it("validates and snapshots a representative 5000-object map", () => {
    const document = createMapDocument({ width: 12000, height: 9000 });
    const template = addAssetObject(document, "starter.crate", {
      x: 100,
      y: 100,
    });
    document.objects = Array.from({ length: 5000 }, (_value, index) => ({
      ...template,
      id: `crate-${index}`,
      x: 100 + (index % 100) * 100,
      y: 100 + Math.floor(index / 100) * 100,
    }));
    const startedAt = performance.now();
    const history = createCommandHistory(document);
    history.execute("Move one", (draft) => {
      draft.objects[0].x += 10;
    });
    expect(history.undo().objects[0].x).toBe(100);
    expect(validateMapDocument(document).valid).toBe(true);
    expect(performance.now() - startedAt).toBeLessThan(3000);
  });
});
