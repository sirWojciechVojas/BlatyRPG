import assert from "node:assert/strict";
import { afterEach, test } from "node:test";
import { authenticate, connectClient, delay, signTicket, startTestServer } from "./helpers.js";

const running = [];
afterEach(async () => Promise.all(running.splice(0).map(({ server }) => server.stop())));

const connect = async (url, userId, instance, capabilities = {}) => {
  const client = await connectClient(url);
  await authenticate(client, signTicket({
    sub: userId,
    auth_session_id: 100 + userId,
    client_instance_id: instance,
    capabilities,
  }), { clientInstanceId: instance });
  return client;
};

const request = {
  v: 1,
  type: "token.move",
  requestId: "token-move-1",
  sceneId: 4,
  tokenId: 9,
  revision: 3,
  x: 300,
  y: 400,
};

test("broadcasts only the token snapshot committed by the backend", async () => {
  let commit;
  const pending = new Promise((resolve) => { commit = resolve; });
  const setup = await startTestServer({}, {
    tokenBackend: { move: async () => pending },
  });
  running.push(setup);
  const sender = await connect(setup.url, 1, "client-instance-0001");
  const recipient = await connect(setup.url, 2, "client-instance-0002");

  sender.send(request);
  await delay(20);
  assert.equal(recipient.history.some((event) => event.type === "token.updated"), false);
  commit({
    token: { id: 9, sceneId: 4, name: "Guard", x: 300, y: 400, revision: 4 },
    publishToPlayers: true,
  });
  const [updated, ack] = await Promise.all([
    recipient.event("token.updated"),
    sender.event("token.ack"),
  ]);
  assert.equal(updated.payload.token.revision, 4);
  assert.ok(updated.sequence > 0);
  assert.equal(ack.payload.requestId, request.requestId);
});

test("does not leak hidden-scene movement to regular campaign members", async () => {
  const setup = await startTestServer({}, {
    tokenBackend: {
      move: async () => ({
        token: { id: 9, sceneId: 4, name: "Secret", x: 1, y: 2, revision: 4 },
        publishToPlayers: false,
      }),
    },
  });
  running.push(setup);
  const sender = await connect(setup.url, 1, "client-instance-0001");
  const regular = await connect(setup.url, 2, "client-instance-0002");
  const gm = await connect(setup.url, 3, "client-instance-0003", { canViewHidden: true });

  sender.send(request);
  await Promise.all([sender.event("token.ack"), gm.event("token.updated")]);
  const marker = await regular.event("sync.marker");
  await delay(30);
  assert.equal(marker.actorUserId, null);
  assert.deepEqual(marker.payload, {});
  assert.equal(regular.history.some((event) => event.type === "token.updated"), false);
});

test("publishes token movement only to selected visible users and managers", async () => {
  const setup = await startTestServer({}, {
    tokenBackend: {
      move: async () => ({
        token: {
          id: 9,
          sceneId: 4,
          name: "Scoped",
          x: 3,
          y: 4,
          revision: 4,
          visibleTo: { mode: "users", userIds: [2] },
        },
        publishToPlayers: true,
      }),
    },
  });
  running.push(setup);
  const sender = await connect(setup.url, 1, "client-instance-0001");
  const selected = await connect(setup.url, 2, "client-instance-0002");
  const manager = await connect(
    setup.url,
    3,
    "client-instance-0003",
    { canManage: true },
  );
  const denied = await connect(setup.url, 4, "client-instance-0004");

  sender.send(request);
  await Promise.all([
    sender.event("token.ack"),
    selected.event("token.updated"),
    manager.event("token.updated"),
    denied.event("sync.marker"),
  ]);
  assert.equal(denied.history.some((event) => event.type === "token.updated"), false);
});
