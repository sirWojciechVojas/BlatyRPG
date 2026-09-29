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
  type: "wall.change",
  requestId: "wall-update-1",
  operation: "update",
  sceneId: 4,
  wallId: 8,
  revision: 1,
  changes: { doorState: "open" },
};

test("broadcasts committed walls without revealing secret metadata", async () => {
  const setup = await startTestServer(
    {},
    {
      wallBackend: {
        change: async () => ({
          operation: "update",
          wall: {
            id: 8,
            sceneId: 4,
            name: "Hidden passage",
            type: "secret",
            x1: 0,
            y1: 50,
            x2: 100,
            y2: 50,
            revision: 2,
            doorState: "open",
            color: "#FF00FF",
            blocksMovement: false,
            blocksSight: true,
            blocksLight: true,
            enabled: true,
            hidden: true,
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
  const [updated, ack, publicUpdate] = await Promise.all([
    gm.event("wall.updated"),
    sender.event("wall.ack"),
    regular.event("wall.updated"),
  ]);
  assert.equal(updated.payload.wall.revision, 2);
  assert.equal(ack.payload.requestId, request.requestId);
  assert.equal(publicUpdate.payload.wall.type, "wall");
  assert.equal(publicUpdate.payload.wall.name, "Wall 8");
  assert.equal(publicUpdate.payload.wall.doorState, null);
  assert.equal(publicUpdate.payload.wall.color, null);
  assert.equal(publicUpdate.payload.wall.hidden, false);
  assert.equal(publicUpdate.payload.wall.blocksSight, true);
  assert.equal(publicUpdate.payload.wall.enabled, true);
});

test("projects secret wall audio only after the authoritative wall publish", async () => {
  const audioCalls = [];
  const setup = await startTestServer(
    {},
    {
      wallBackend: {
        change: async () => ({
          operation: "update",
          sound: "open",
          wall: {
            id: 8,
            sceneId: 4,
            name: "Hidden passage",
            type: "secret",
            doorType: "secret",
            x1: 0,
            y1: 50,
            x2: 100,
            y2: 50,
            revision: 2,
            doorState: "open",
            soundConfig: { version: 2, rules: [{ geometry: { points: [0.5] } }] },
          },
        }),
        audioState: async (session, sceneId) => {
          audioCalls.push(["state", session.userId, sceneId]);
          return { sceneId, activeLoops: [], serverTime: 1000 };
        },
        audioCue: async (session, sceneId, wallId, cue) => {
          audioCalls.push(["cue", session.userId, sceneId, wallId, cue]);
          return {
            sceneId,
            serverTime: 1000,
            items: [{
              playbackId: `opaque-${session.userId}`,
              audio: { id: 5, title: "Door", url: "/safe/audio/5" },
              volume: 0.5,
              loop: false,
              fadeInMs: 10,
              fadeOutMs: 20,
            }],
          };
        },
      },
    },
  );
  running.push(setup);
  const sender = await connect(setup.url, 1, "client-instance-0001", {
    canManage: true,
  });
  const regular = await connect(setup.url, 2, "client-instance-0002");

  sender.send(request);
  await sender.event("wall.ack");
  const [state, cue] = await Promise.all([
    regular.event("wall.audio.state"),
    regular.event("wall.audio.cue"),
  ]);

  assert.deepEqual(state.payload.activeLoops, []);
  assert.equal(cue.payload.items[0].playbackId, "opaque-2");
  assert.equal(cue.payload.items[0].volume, 0.5);
  assert.equal(JSON.stringify(cue.payload).includes("geometry"), false);
  assert.deepEqual(audioCalls, [
    ["state", 2, 4],
    ["cue", 2, 4, 8, "open"],
  ]);
});
