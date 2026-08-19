import assert from "node:assert/strict";
import test from "node:test";
import { ProtocolError, parseAuthenticatedMessage } from "../src/protocol.js";

const parses = (message) =>
  parseAuthenticatedMessage({ v: 1, requestId: "request-1", ...message });

test("normalizes a chat send without accepting identity fields", () => {
  const parsed = parses({
    type: "chat.send",
    clientNonce: "550E8400-E29B-41D4-A716-446655440000",
    body: "  Hello\r\nparty  ",
  });
  assert.equal(parsed.body, "Hello\nparty");
  assert.equal(parsed.clientNonce, "550e8400-e29b-41d4-a716-446655440000");

  assert.throws(
    () => parses({
      type: "chat.send",
      clientNonce: "550e8400-e29b-41d4-a716-446655440000",
      body: "hello",
      campaignId: 99,
    }),
    (error) => error instanceof ProtocolError && error.code === "unexpected_field",
  );
});

test("validates chat cursors, limits and message bodies", () => {
  assert.deepEqual(parses({
    type: "chat.sync",
    afterRevision: 12,
    limit: 20,
  }), {
    type: "chat.sync",
    requestId: "request-1",
    afterRevision: 12,
    beforeRevision: null,
    limit: 20,
  });

  for (const message of [
    { type: "chat.sync", afterRevision: 2, beforeRevision: 3 },
    { type: "chat.sync", limit: 26 },
    {
      type: "chat.send",
      clientNonce: "550e8400-e29b-41d4-a716-446655440000",
      body: "\u0000",
    },
  ]) assert.throws(() => parses(message), ProtocolError);
});
