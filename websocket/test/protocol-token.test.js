import assert from "node:assert/strict";
import test from "node:test";
import { ProtocolError, parseAuthenticatedMessage } from "../src/protocol.js";

test("validates token movement without accepting client campaign scope", () => {
  const move = {
    v: 1,
    type: "token.move",
    requestId: "move-1",
    sceneId: 4,
    tokenId: 9,
    revision: 3,
    x: 120.5,
    y: 240,
    waypoints: [{ x: 180, y: 200 }],
  };
  assert.deepEqual(parseAuthenticatedMessage(move), {
    type: "token.move",
    requestId: "move-1",
    sceneId: 4,
    tokenId: 9,
    revision: 3,
    x: 120.5,
    y: 240,
    waypoints: [{ x: 180, y: 200 }],
  });
  assert.throws(
    () => parseAuthenticatedMessage({ ...move, campaignId: 99 }),
    (error) => error instanceof ProtocolError && error.code === "unexpected_field",
  );
  assert.throws(
    () => parseAuthenticatedMessage({ ...move, x: Number.POSITIVE_INFINITY }),
    (error) => error instanceof ProtocolError && error.code === "token_x_invalid",
  );
  assert.throws(
    () => parseAuthenticatedMessage({ ...move, waypoints: [{ x: 1 }] }),
    (error) =>
      error instanceof ProtocolError && error.code === "token_waypoint_y_invalid",
  );
});

test("validates one bounded group movement command", () => {
  const group = {
    v: 1,
    type: "token.move.group",
    requestId: "group-1",
    sceneId: 4,
    moves: [
      { tokenId: 9, revision: 3, x: 100, y: 200, waypoints: [] },
      { tokenId: 10, revision: 7, x: 300, y: 400, waypoints: [] },
    ],
  };

  assert.deepEqual(parseAuthenticatedMessage(group), {
    type: "token.move.group",
    requestId: "group-1",
    sceneId: 4,
    moves: group.moves,
  });
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        ...group,
        moves: [group.moves[0], { ...group.moves[1], tokenId: 9 }],
      }),
    (error) =>
      error instanceof ProtocolError && error.code === "token_group_duplicate",
  );
});

test("accepts realtime token changes", () => {
  const change = {
    v: 1,
    type: "token.change",
    requestId: "change-1",
    sceneId: 4,
    tokenId: 9,
    revision: 3,
    changes: {
      rotation: 72.5,
      facing: 185,
      resources: { bars: [], bubbles: [{ enabled: true, value: 7 }] },
    },
  };
  assert.deepEqual(parseAuthenticatedMessage(change), {
    type: "token.change",
    requestId: "change-1",
    sceneId: 4,
    tokenId: 9,
    revision: 3,
    changes: {
      rotation: 72.5,
      facing: 185,
      resources: { bars: [], bubbles: [{ enabled: true, value: 7 }] },
    },
  });
  assert.throws(
    () => parseAuthenticatedMessage({ ...change, changes: { x: 100 } }),
    (error) => error instanceof ProtocolError && error.code === "unexpected_field",
  );
  assert.throws(
    () => parseAuthenticatedMessage({ ...change, changes: {} }),
    (error) => error instanceof ProtocolError && error.code === "token_changes_invalid",
  );
  assert.throws(
    () => parseAuthenticatedMessage({ ...change, changes: { facing: NaN } }),
    (error) => error instanceof ProtocolError && error.code === "token_facing_invalid",
  );
});

test("validates movement approval requests and GM decisions", () => {
  const request = parseAuthenticatedMessage({
    v: 1,
    type: "token.movement.request",
    requestId: "movement-request-1",
    sceneId: 4,
    tokenId: 9,
    revision: 3,
    x: 500,
    y: 600,
    waypoints: [],
  });
  assert.equal(request.type, "token.movement.request");
  assert.equal(request.tokenId, 9);

  assert.deepEqual(
    parseAuthenticatedMessage({
      v: 1,
      type: "token.movement.resolve",
      requestId: "movement-resolve-1",
      movementRequestId: 22,
      decision: "approve",
    }),
    {
      type: "token.movement.resolve",
      requestId: "movement-resolve-1",
      movementRequestId: 22,
      decision: "approve",
    },
  );
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "token.movement.resolve",
        requestId: "movement-resolve-2",
        movementRequestId: 22,
        decision: "force",
      }),
    (error) =>
      error instanceof ProtocolError && error.code === "movement_decision_invalid",
  );
});
