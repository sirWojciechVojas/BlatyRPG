import assert from "node:assert/strict";
import { afterEach, test } from "node:test";
import {
  authenticate,
  connectClient,
  delay,
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

test("publishes synchronized tokens but keeps link metadata with managers", async () => {
  let received;
  const setup = await startTestServer(
    {},
    {
      tokenSyncBackend: {
        command: async (_session, request) => {
          received = request;
          return {
            synchronizedTokens: [
              {
                token: {
                  id: 10,
                  sceneId: 5,
                  characterId: 12,
                  name: "Hero",
                  revision: 8,
                },
                publishToPlayers: true,
                changedFields: ["name", "resources"],
              },
            ],
            links: [{ id: 31, sourceTokenId: 9, targetTokenId: 10 }],
          };
        },
      },
    },
  );
  running.push(setup);
  const gameMaster = await connect(setup.url, 1, "client-instance-0001", {
    canManage: true,
    canManageTokenSync: true,
  });
  const player = await connect(setup.url, 2, "client-instance-0002");

  gameMaster.send({
    v: 1,
    type: "token.sync.command",
    requestId: "token-sync-create-1",
    action: "createLinks",
    data: {
      sourceTokenId: 9,
      sourceRevision: 3,
      targets: [{ tokenId: 10, revision: 7 }],
    },
  });

  const [updated, metadata, ack, playerMarker] = await Promise.all([
    player.event("token.updated"),
    gameMaster.event("token.sync.changed"),
    gameMaster.event("token.sync.ack"),
    player.event("sync.marker"),
  ]);
  assert.equal(received.action, "createLinks");
  assert.equal(updated.payload.token.id, 10);
  assert.deepEqual(metadata.payload.tokenIds, [10]);
  assert.deepEqual(ack.payload.synchronizedTokenIds, [10]);
  assert.deepEqual(playerMarker.payload, {});
  await delay(20);
  assert.equal(
    player.history.some((event) => event.type === "token.sync.changed"),
    false,
  );
});

test("publishes live targets after an ordinary source token change", async () => {
  const setup = await startTestServer(
    {},
    {
      tokenBackend: {
        change: async () => ({
          token: { id: 9, sceneId: 4, name: "Renamed", revision: 4 },
          publishToPlayers: true,
          synchronizedTokens: [
            {
              token: { id: 10, sceneId: 5, name: "Renamed", revision: 8 },
              publishToPlayers: true,
              changedFields: ["name"],
            },
          ],
        }),
      },
    },
  );
  running.push(setup);
  const player = await connect(setup.url, 2, "client-instance-0002");
  const observer = await connect(setup.url, 3, "client-instance-0003");

  player.send({
    v: 1,
    type: "token.change",
    requestId: "token-change-live-1",
    sceneId: 4,
    tokenId: 9,
    revision: 3,
    changes: { name: "Renamed" },
  });

  await player.event("token.ack");
  await delay(20);
  assert.deepEqual(
    observer.history
      .filter((event) => event.type === "token.updated")
      .map((event) => event.payload.token.id),
    [9, 10],
  );
});

test("does not reveal an inaccessible live target to the source editor", async () => {
  const setup = await startTestServer(
    {},
    {
      tokenBackend: {
        change: async () => ({
          token: { id: 9, sceneId: 4, name: "Renamed", revision: 4 },
          publishToPlayers: true,
          synchronizedTokens: [
            {
              token: { id: 10, sceneId: 5, name: "Secret", revision: 8 },
              publishToPlayers: false,
              changedFields: ["name"],
            },
          ],
        }),
      },
    },
  );
  running.push(setup);
  const player = await connect(setup.url, 2, "client-instance-0002");
  const gameMaster = await connect(setup.url, 1, "client-instance-0001", {
    canManage: true,
    canManageTokenSync: true,
  });

  player.send({
    v: 1,
    type: "token.change",
    requestId: "token-change-secret-live-1",
    sceneId: 4,
    tokenId: 9,
    revision: 3,
    changes: { name: "Renamed" },
  });

  await player.event("token.ack");
  await gameMaster.event("token.sync.changed");
  await delay(20);
  assert.deepEqual(
    player.history
      .filter((event) => event.type === "token.updated")
      .map((event) => event.payload.token.id),
    [9],
  );
  assert.deepEqual(
    gameMaster.history
      .filter((event) => event.type === "token.updated")
      .map((event) => event.payload.token.id),
    [9, 10],
  );
});
