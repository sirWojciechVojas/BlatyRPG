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
