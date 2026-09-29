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

test("synchronizes fog only with its player and game masters", async () => {
  const setup = await startTestServer();
  running.push(setup);
  const player = await connect(setup.url, 2, "client-instance-0002");
  const other = await connect(setup.url, 3, "client-instance-0003");
  const gm = await connect(setup.url, 4, "client-instance-0004", {
    canManage: true,
  });
  player.send({
    v: 1,
    type: "fog.sync",
    requestId: "fog-sync-1",
    sceneId: 7,
    userId: 2,
    revision: 5,
  });
  const [mine, manager, marker] = await Promise.all([
    player.event("fog.updated"),
    gm.event("fog.updated"),
    other.event("sync.marker"),
  ]);
  assert.equal(mine.payload.userId, 2);
  assert.equal(manager.payload.revision, 5);
  assert.deepEqual(marker.payload, {});
});

test("rejects a player attempting to synchronize another user's fog", async () => {
  const setup = await startTestServer();
  running.push(setup);
  const player = await connect(setup.url, 2, "client-instance-0002");
  player.send({
    v: 1,
    type: "fog.sync",
    requestId: "fog-sync-2",
    sceneId: 7,
    userId: 9,
    revision: 1,
  });
  const error = await player.event("fog.error");
  assert.equal(error.payload.code, "forbidden");
});
