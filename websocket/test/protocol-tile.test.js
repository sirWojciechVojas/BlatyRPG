import assert from "node:assert/strict";
import test from "node:test";
import { ProtocolError, parseAuthenticatedMessage } from "../src/protocol.js";

test("validates image and video tile changes", () => {
  const create = {
    v: 1,
    type: "tile.change",
    requestId: "tile-create-1",
    operation: "create",
    sceneId: 4,
    changes: {
      name: "Fire",
      assetUrl: "https://cdn.example.test/fire.webm",
      mediaType: "video",
      layer: "foreground",
      x: 100,
      y: 150,
      width: 200,
      height: 200,
      loop: true,
    },
  };
  assert.deepEqual(parseAuthenticatedMessage(create), {
    type: "tile.change",
    requestId: "tile-create-1",
    operation: "create",
    sceneId: 4,
    changes: create.changes,
  });
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        ...create,
        changes: { ...create.changes, assetUrl: "javascript:alert(1)" },
      }),
    (error) =>
      error instanceof ProtocolError && error.code === "tile_asset_url_invalid",
  );
});

test("requires tile geometry and optimistic revisions", () => {
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "tile.change",
        requestId: "tile-create-2",
        operation: "create",
        sceneId: 4,
        changes: { assetUrl: "/crate.webp" },
      }),
    (error) =>
      error instanceof ProtocolError && error.code === "tile_geometry_required",
  );
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "tile.change",
        requestId: "tile-update-1",
        operation: "update",
        sceneId: 4,
        tileId: 9,
        changes: { opacity: 0.5 },
      }),
    (error) =>
      error instanceof ProtocolError && error.code === "tile_change_invalid",
  );
});
