import assert from "node:assert/strict";
import test from "node:test";
import { ProtocolError, parseAuthenticatedMessage } from "../src/protocol.js";

test("validates combat commands and movement assignments", () => {
  assert.deepEqual(
    parseAuthenticatedMessage({
      v: 1,
      type: "combat.command",
      requestId: "combat-next-1",
      sceneId: 4,
      command: { action: "next", revision: 3 },
    }),
    {
      type: "combat.command",
      requestId: "combat-next-1",
      sceneId: 4,
      command: { action: "next", revision: 3 },
    },
  );

  assert.deepEqual(
    parseAuthenticatedMessage({
      v: 1,
      type: "combat.command",
      requestId: "combat-movement-1",
      sceneId: 4,
      command: {
        action: "setMovement",
        tokenId: 9,
        tokenRevision: 2,
        movementRange: 8,
        movementPoints: 5,
        movementResetMode: "turn",
      },
    }).command.movementPoints,
    5,
  );

  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "combat.command",
        requestId: "combat-bad-1",
        sceneId: 4,
        command: { action: "next", revision: 3, admin: true },
      }),
    (error) => error instanceof ProtocolError && error.code === "unexpected_field",
  );
});
