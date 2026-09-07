import assert from "node:assert/strict";
import test from "node:test";
import { ProtocolError, parseAuthenticatedMessage } from "../src/protocol.js";

const batchId = "8f1c0c29-29cd-4e5e-9d3a-e8e9d2b2b8aa";

test("handout notification command contains only a delivery batch", () => {
  assert.deepEqual(
    parseAuthenticatedMessage({
      v: 1,
      type: "handout.notify",
      requestId: "handout-1",
      batchId,
    }),
    { type: "handout.notify", requestId: "handout-1", batchId },
  );
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "handout.notify",
        requestId: "handout-1",
        batchId,
        handoutId: 99,
      }),
    (error) => error instanceof ProtocolError && error.code === "unexpected_field",
  );
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "handout.notify",
        requestId: "handout-1",
        batchId: "invalid",
      }),
    (error) =>
      error instanceof ProtocolError && error.code === "handout_batch_invalid",
  );
});
