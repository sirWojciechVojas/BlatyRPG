import assert from "node:assert/strict";
import { afterEach, test } from "node:test";
import {
  authenticate,
  connectClient,
  signTicket,
  startTestServer,
} from "./helpers.js";

const running = [];
afterEach(async () =>
  Promise.all(running.splice(0).map(({ server }) => server.stop())),
);

const connect = async (url, userId, instance, capabilities = {}) => {
  const client = await connectClient(url);
  await authenticate(
    client,
    signTicket({
      sub: userId,
      auth_session_id: 100 + userId,
      client_instance_id: instance,
      capabilities,
    }),
    { clientInstanceId: instance },
  );
  return client;
};

const request = (requestId, revision, changes) => ({
  v: 1,
  type: "tile.change",
  requestId,
  operation: "update",
  sceneId: 4,
  tileId: 9,
  revision,
  changes,
});

test("broadcasts visible tiles and removes newly hidden tiles from players", async () => {
  let revision = 1;
  const setup = await startTestServer(
    {},
    {
      tileBackend: {
        change: async (_session, message) => ({
          operation: "update",
          tile: {
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
            hidden: message.changes.hidden === true,
            revision: ++revision,
          },
        }),
      },
    },
  );
  running.push(setup);
  const sender = await connect(setup.url, 1, "client-instance-0001");
  const player = await connect(setup.url, 2, "client-instance-0002");
  const gm = await connect(setup.url, 3, "client-instance-0003", {
    canManage: true,
  });

  sender.send(request("tile-opacity-1", 1, { opacity: 0.8 }));
  const [playerUpdate, gmUpdate] = await Promise.all([
    player.event("tile.updated"),
    gm.event("tile.updated"),
  ]);
  assert.equal(playerUpdate.payload.tile.opacity, 0.8);
  assert.equal(gmUpdate.payload.tile.revision, 2);

  sender.send(request("tile-hidden-1", 2, { hidden: true }));
  const [playerDelete, gmHidden] = await Promise.all([
    player.event("tile.deleted"),
    gm.event("tile.updated"),
  ]);
  assert.equal(playerDelete.payload.tileId, 9);
  assert.equal(gmHidden.payload.tile.hidden, true);
});
