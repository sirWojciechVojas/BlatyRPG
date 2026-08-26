import assert from "node:assert/strict";
import { afterEach, test } from "node:test";
import { authenticate, connectClient, signTicket, startTestServer } from "./helpers.js";

const running = [];
afterEach(async () => Promise.all(running.splice(0).map(({ server }) => server.stop())));

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

test("broadcasts combat invalidation after the authoritative command", async () => {
  let received;
  const setup = await startTestServer({}, {
    combatBackend: {
      command: async (_session, payload) => {
        received = payload;
        return {
          action: "next",
          movementChanged: true,
          publishToPlayers: true,
        };
      },
    },
  });
  running.push(setup);
  const gm = await connect(setup.url, 1, "combat-instance-0001", {
    canManage: true,
  });
  const player = await connect(setup.url, 2, "combat-instance-0002");

  gm.send({
    v: 1,
    type: "combat.command",
    requestId: "combat-next-1",
    sceneId: 4,
    command: { action: "next", revision: 3 },
  });
  const [updated, ack] = await Promise.all([
    player.event("combat.updated"),
    gm.event("combat.ack"),
  ]);

  assert.equal(received.command.action, "next");
  assert.equal(updated.payload.movementChanged, true);
  assert.equal(updated.payload.sceneId, 4);
  assert.equal(ack.payload.requestId, "combat-next-1");
});
