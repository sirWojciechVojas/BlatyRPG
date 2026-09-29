import assert from "node:assert/strict";
import { test } from "node:test";
import { parseAuthenticatedMessage } from "../src/protocol.js";

test("validates polygonal region changes without client campaign scope", () => {
  assert.deepEqual(
    parseAuthenticatedMessage({
      v: 1,
      type: "region.change",
      requestId: "region-1",
      operation: "create",
      sceneId: 4,
      changes: {
        name: "Dark interior",
        polygons: [[
          { x: 0, y: 0 },
          { x: 100, y: 0 },
          { x: 100, y: 100 },
        ]],
        darknessMode: "add",
        darknessValue: 0.6,
        disableGlobalIllumination: true,
      },
    }),
    {
      type: "region.change",
      requestId: "region-1",
      operation: "create",
      sceneId: 4,
      changes: {
        name: "Dark interior",
        polygons: [[
          { x: 0, y: 0 },
          { x: 100, y: 0 },
          { x: 100, y: 100 },
        ]],
        darknessMode: "add",
        darknessValue: 0.6,
        disableGlobalIllumination: true,
      },
    },
  );
});

test("requires a bounded polygon and optimistic revision", () => {
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "region.change",
        requestId: "region-bad",
        operation: "create",
        sceneId: 4,
        changes: { polygons: [[{ x: 0, y: 0 }, { x: 1, y: 1 }]] },
      }),
    /region_polygon_invalid/,
  );
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "region.change",
        requestId: "region-update",
        operation: "update",
        sceneId: 4,
        regionId: 9,
        changes: { darknessValue: 0.2 },
      }),
    /region_change_invalid/,
  );
});
