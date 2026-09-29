import assert from "node:assert/strict";
import test from "node:test";
import { parseAuthenticatedMessage } from "../src/protocol.js";

test("accepts a scoped wall audio synchronization request", () => {
  assert.deepEqual(
    parseAuthenticatedMessage({
      v: 1,
      type: "wall.audio.sync",
      requestId: "wall-audio-1",
      sceneId: 4,
      selectedTokenId: 9,
    }),
    {
      type: "wall.audio.sync",
      requestId: "wall-audio-1",
      sceneId: 4,
      selectedTokenId: 9,
    },
  );
});

test("allows players to sync without supplying a selected token", () => {
  assert.deepEqual(
    parseAuthenticatedMessage({
      v: 1,
      type: "wall.audio.sync",
      requestId: "wall-audio-2",
      sceneId: 4,
    }),
    {
      type: "wall.audio.sync",
      requestId: "wall-audio-2",
      sceneId: 4,
      selectedTokenId: null,
    },
  );
});

test("rejects client-supplied listener coordinates", () => {
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "wall.audio.sync",
        requestId: "wall-audio-3",
        sceneId: 4,
        x: 100,
        y: 200,
      }),
    (error) => error.code === "unexpected_field",
  );
});
