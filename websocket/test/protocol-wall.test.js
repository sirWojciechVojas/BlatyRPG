import assert from "node:assert/strict";
import test from "node:test";
import { ProtocolError, parseAuthenticatedMessage } from "../src/protocol.js";

test("validates wall changes without accepting client campaign scope", () => {
  const create = {
    v: 1,
    type: "wall.change",
    requestId: "wall-create-1",
    operation: "create",
    sceneId: 4,
    changes: {
      name: "North gate",
      type: "door",
      x1: 0,
      y1: 50,
      x2: 100,
      y2: 50,
      color: "#33aaff",
      enabled: true,
    },
  };
  assert.deepEqual(parseAuthenticatedMessage(create), {
    type: "wall.change",
    requestId: "wall-create-1",
    operation: "create",
    sceneId: 4,
    changes: {
      name: "North gate",
      type: "door",
      x1: 0,
      y1: 50,
      x2: 100,
      y2: 50,
      color: "#33AAFF",
      enabled: true,
    },
  });
  assert.throws(
    () => parseAuthenticatedMessage({ ...create, campaignId: 99 }),
    (error) => error instanceof ProtocolError && error.code === "unexpected_field",
  );
  assert.throws(
    () => parseAuthenticatedMessage({ ...create, changes: { x1: 0 } }),
    (error) => error instanceof ProtocolError && error.code === "wall_geometry_required",
  );
});

test("requires optimistic revisions for wall updates", () => {
  assert.throws(
    () => parseAuthenticatedMessage({
      v: 1,
      type: "wall.change",
      requestId: "wall-update-1",
      operation: "update",
      sceneId: 4,
      wallId: 8,
      changes: { doorState: "open" },
    }),
    (error) => error instanceof ProtocolError && error.code === "wall_change_invalid",
  );
});

test("accepts window wall segments", () => {
  const message = parseAuthenticatedMessage({
    v: 1,
    type: "wall.change",
    requestId: "window-create-1",
    operation: "create",
    sceneId: 4,
    changes: {
      type: "window",
      x1: 10,
      y1: 20,
      x2: 50,
      y2: 20,
      blocksMovement: true,
      blocksSight: false,
      blocksLight: false,
    },
  });

  assert.equal(message.changes.type, "window");
  assert.equal(message.changes.blocksSight, false);
  assert.equal(message.changes.blocksLight, false);
});
