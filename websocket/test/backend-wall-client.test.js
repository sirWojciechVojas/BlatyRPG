import assert from "node:assert/strict";
import test from "node:test";
import { BackendWallClient, BackendWallError } from "../src/backend-wall-client.js";
import { testConfig } from "./helpers.js";

const session = {
  campaignId: 7,
  clientInstanceId: "client-instance-0001",
  realtimeTicket: "secret-ticket",
};

test("forwards wall writes using authoritative session scope", async () => {
  let call;
  const client = new BackendWallClient(testConfig(), {
    fetch: async (url, options) => {
      call = { url, options };
      return new Response(JSON.stringify({
        wall: {
          id: 8, sceneId: 4, type: "door", x1: 0, y1: 50,
          x2: 100, y2: 50, revision: 2, doorState: "open",
        },
      }), { status: 200 });
    },
  });

  const result = await client.change(session, {
    operation: "update",
    sceneId: 4,
    wallId: 8,
    revision: 1,
    changes: { doorState: "open" },
  });
  assert.equal(call.url, "http://backend.internal/api/internal/realtime/campaigns/7/walls/change");
  assert.equal(call.options.headers.Authorization, "Realtime secret-ticket");
  assert.deepEqual(JSON.parse(call.options.body), {
    operation: "update",
    sceneId: 4,
    wallId: 8,
    revision: 1,
    changes: { doorState: "open" },
  });
  assert.equal(result.wall.revision, 2);
});

test("fails closed on malformed committed walls", async () => {
  const client = new BackendWallClient(testConfig(), {
    fetch: async () => new Response(JSON.stringify({ wall: { id: 8 } }), { status: 200 }),
  });
  await assert.rejects(
    client.change(session, {
      operation: "update", sceneId: 4, wallId: 8, revision: 1,
      changes: { doorState: "open" },
    }),
    (error) => error instanceof BackendWallError && error.status === 502,
  );
});
