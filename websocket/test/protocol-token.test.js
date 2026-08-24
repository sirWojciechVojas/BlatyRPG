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
  };
  assert.deepEqual(parseAuthenticatedMessage(move), {
    type: "token.move",
    requestId: "move-1",
    sceneId: 4,
    tokenId: 9,
    revision: 3,
    x: 120.5,
    y: 240,
  });
  assert.throws(
    () => parseAuthenticatedMessage({ ...move, campaignId: 99 }),
    (error) => error instanceof ProtocolError && error.code === "unexpected_field",
  );
  assert.throws(
    () => parseAuthenticatedMessage({ ...move, x: Number.POSITIVE_INFINITY }),
    (error) => error instanceof ProtocolError && error.code === "token_x_invalid",
  );
});
