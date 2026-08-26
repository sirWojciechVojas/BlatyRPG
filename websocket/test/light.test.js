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

const request = {
  v: 1,
  type: "light.change",
  requestId: "light-update-1",
  operation: "update",
  sceneId: 4,
  lightId: 8,
  revision: 1,
  changes: { lumens: 500 },
};

test("broadcasts committed light effects to every campaign session", async () => {
  const setup = await startTestServer(
    {},
    {
      lightBackend: {
        change: async () => ({
          operation: "update",
          light: {
            id: 8,
            sceneId: 4,
            x: 100,
            y: 150,
            brightRadius: 200,
            dimRadius: 400,
            color: "#FFD27A",
            intensity: 0.5,
            lumens: 500,
            enabled: true,
            hidden: false,
            revision: 2,
          },
        }),
      },
    },
  );
  running.push(setup);
  const sender = await connect(setup.url, 1, "client-instance-0001");
  const regular = await connect(setup.url, 2, "client-instance-0002");
  const gm = await connect(setup.url, 3, "client-instance-0003", {
    canManage: true,
  });

  sender.send(request);
  const [updated, ack, regularUpdate] = await Promise.all([
    gm.event("light.updated"),
    sender.event("light.ack"),
    regular.event("light.updated"),
  ]);
  assert.equal(updated.payload.light.revision, 2);
  assert.equal(ack.payload.requestId, request.requestId);
  assert.equal(regularUpdate.payload.light.lumens, 500);
});

test("broadcasts the authoritative global illumination snapshot", async () => {
  const setup = await startTestServer(
    {},
    {
      lightBackend: {
        change: async () => ({
          operation: "syncScene",
          scene: {
            id: 4,
            name: "Crypt",
            globalLightLevel: 0.2,
            fogExploration: true,
            revision: 3,
          },
        }),
      },
    },
  );
  running.push(setup);
  const sender = await connect(setup.url, 1, "client-instance-0001");
  const regular = await connect(setup.url, 2, "client-instance-0002");

  sender.send({
    v: 1,
    type: "light.change",
    requestId: "scene-lighting-sync-1",
    operation: "syncScene",
    sceneId: 4,
  });
  const [updated, ack] = await Promise.all([
    regular.event("scene.updated"),
    sender.event("light.ack"),
  ]);
  assert.equal(updated.payload.scene.globalLightLevel, 0.2);
  assert.equal(ack.payload.revision, 3);
});
