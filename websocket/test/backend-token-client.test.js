import assert from "node:assert/strict";
import test from "node:test";
import { BackendTokenClient, BackendTokenError } from "../src/backend-token-client.js";
import { testConfig } from "./helpers.js";

const session = {
  campaignId: 7,
  clientInstanceId: "client-instance-0001",
  realtimeTicket: "secret-ticket",
};

test("forwards token moves using authoritative session scope", async () => {
  let call;
  const client = new BackendTokenClient(testConfig(), {
    fetch: async (url, options) => {
      call = { url, options };
      return new Response(JSON.stringify({
        token: {
          id: 9,
          sceneId: 4,
          name: "Guard",
          x: 30,
          y: 40,
          rotation: 90,
          revision: 4,
        },
        visibility: { publishToPlayers: true },
      }), { status: 200 });
    },
  });

  const result = await client.move(session, {
    sceneId: 4, tokenId: 9, revision: 3, x: 30, y: 40,
  });
  assert.equal(call.url, "http://backend.internal/api/internal/realtime/campaigns/7/tokens/move");
  assert.equal(call.options.headers.Authorization, "Realtime secret-ticket");
  assert.deepEqual(JSON.parse(call.options.body), {
    sceneId: 4, tokenId: 9, revision: 3, x: 30, y: 40,
  });
  assert.equal(result.token.revision, 4);
  assert.equal(result.token.facing, 90);
  assert.equal(result.publishToPlayers, true);
});

test("fails closed on malformed committed tokens", async () => {
  const client = new BackendTokenClient(testConfig(), {
    fetch: async () => new Response(JSON.stringify({ token: { id: 9 } }), { status: 200 }),
  });
  await assert.rejects(
    client.move(session, { sceneId: 4, tokenId: 9, revision: 3, x: 1, y: 2 }),
    (error) => error instanceof BackendTokenError && error.status === 502,
  );
});
