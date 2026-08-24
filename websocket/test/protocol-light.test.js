import assert from "node:assert/strict";
import test from "node:test";
import { ProtocolError, parseAuthenticatedMessage } from "../src/protocol.js";

test("validates light changes without accepting client campaign scope", () => {
  const create = {
    v: 1,
    type: "light.change",
    requestId: "light-create-1",
    operation: "create",
    sceneId: 4,
    changes: {
      x: 100,
      y: 150,
      brightRadius: 200,
      dimRadius: 400,
      color: "#FFD27A",
    },
  };
  assert.deepEqual(parseAuthenticatedMessage(create), {
    type: "light.change",
    requestId: "light-create-1",
    operation: "create",
    sceneId: 4,
    changes: create.changes,
  });
  assert.throws(
    () => parseAuthenticatedMessage({ ...create, campaignId: 99 }),
    (error) =>
      error instanceof ProtocolError && error.code === "unexpected_field",
  );
  assert.throws(
    () => parseAuthenticatedMessage({ ...create, changes: { x: 100 } }),
    (error) =>
      error instanceof ProtocolError &&
      error.code === "light_geometry_required",
  );
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        ...create,
        changes: { x: 100, y: 150, brightRadius: 500, dimRadius: 100 },
      }),
    (error) =>
      error instanceof ProtocolError && error.code === "light_radius_invalid",
  );
});

test("requires optimistic revisions for light updates", () => {
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "light.change",
        requestId: "light-update-1",
        operation: "update",
        sceneId: 4,
        lightId: 8,
        changes: { enabled: false },
      }),
    (error) =>
      error instanceof ProtocolError && error.code === "light_change_invalid",
  );
});
