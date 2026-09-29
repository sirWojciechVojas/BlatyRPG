import assert from "node:assert/strict";
import { test } from "node:test";
import { parseAuthenticatedMessage } from "../src/protocol.js";

test("validates compact fog synchronization envelopes", () => {
  assert.deepEqual(
    parseAuthenticatedMessage({
      v: 1,
      type: "fog.sync",
      requestId: "fog-1",
      sceneId: 4,
      userId: 8,
      revision: 3,
    }),
    {
      type: "fog.sync",
      requestId: "fog-1",
      sceneId: 4,
      userId: 8,
      revision: 3,
    },
  );
  assert.deepEqual(
    parseAuthenticatedMessage({
      v: 1,
      type: "fog.sync",
      requestId: "fog-shared",
      sceneId: 4,
      userId: 8,
      revision: 4,
      shared: true,
    }),
    {
      type: "fog.sync",
      requestId: "fog-shared",
      sceneId: 4,
      userId: 8,
      revision: 4,
      shared: true,
    },
  );
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "fog.sync",
        requestId: "fog-1",
        sceneId: 4,
        userId: 8,
        revision: 3,
        exploredRanges: [[0, 999]],
      }),
    /unexpected_field/,
  );
});
