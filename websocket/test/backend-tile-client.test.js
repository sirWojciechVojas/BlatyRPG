import assert from "node:assert/strict";
import test from "node:test";
import {
  BackendTileClient,
  BackendTileError,
} from "../src/backend-tile-client.js";
import { testConfig } from "./helpers.js";

const session = {
  campaignId: 7,
  clientInstanceId: "client-instance-0001",
  realtimeTicket: "secret-ticket",
};

const committedTile = {
  id: 9,
  sceneId: 4,
  name: "Fire",
  assetUrl: "/fire.webm",
  mediaType: "video",
  layer: "foreground",
  x: 100,
  y: 150,
  width: 200,
  height: 200,
  rotation: 0,
  opacity: 0.8,
  sortOrder: 1,
  autoplay: true,
  loop: true,
  muted: true,
  revision: 2,
};

test("forwards tile writes using authoritative session scope", async () => {
  let call;
  const client = new BackendTileClient(testConfig(), {
    fetch: async (url, options) => {
      call = { url, options };
      return new Response(JSON.stringify({ tile: committedTile }), {
        status: 200,
      });
    },
  });
  const result = await client.change(session, {
    operation: "update",
    sceneId: 4,
    tileId: 9,
    revision: 1,
    changes: { opacity: 0.8 },
  });

  assert.equal(
    call.url,
    "http://backend.internal/api/internal/realtime/campaigns/7/tiles/change",
  );
  assert.equal(call.options.headers.Authorization, "Realtime secret-ticket");
  assert.deepEqual(JSON.parse(call.options.body), {
    operation: "update",
    sceneId: 4,
    tileId: 9,
    revision: 1,
    changes: { opacity: 0.8 },
  });
  assert.equal(result.tile.revision, 2);
});

test("fails closed on malformed committed tiles", async () => {
  const client = new BackendTileClient(testConfig(), {
    fetch: async () =>
      new Response(JSON.stringify({ tile: { id: 9 } }), { status: 200 }),
  });
  await assert.rejects(
    client.change(session, {
      operation: "update",
      sceneId: 4,
      tileId: 9,
      revision: 1,
      changes: { opacity: 0.5 },
    }),
    (error) => error instanceof BackendTileError && error.status === 502,
  );
});
