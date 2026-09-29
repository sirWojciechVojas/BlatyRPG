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

const snapshot = (sceneId = 4) => ({
  scenes: {
    items: [{ id: sceneId, campaignId: 7, name: "Ruins" }],
    activeSceneId: sceneId,
    capabilities: { canManage: false, canViewHidden: false },
  },
  scene: { id: sceneId, campaignId: 7, name: "Ruins" },
  capabilities: { canManage: false, canViewHidden: false },
  tokens: { items: [], capabilities: { canCreate: false } },
  walls: { items: [], capabilities: { canManage: false } },
  lights: { items: [], capabilities: { canManage: false } },
  tiles: { items: [], capabilities: { canManage: false } },
  combat: { combat: null, capabilities: { canManage: false } },
  fog: { sceneId, userId: 1, exploredRanges: [], forcedHiddenRanges: [] },
  movementRequests: { items: [], capabilities: { canResolve: false } },
});

test("delivers a permission-filtered scene snapshot with sync.request", async () => {
  const snapshotBackend = {
    get: async (session, sceneId) => {
      assert.equal(session.campaignId, 7);
      assert.equal(sceneId, 4);
      return snapshot(sceneId);
    },
  };
  const setup = await startTestServer({}, { snapshotBackend });
  running.push(setup);
  const client = await connectClient(setup.url);
  await authenticate(client, signTicket());

  client.send({ v: 1, type: "sync.request", lastSequence: 0, sceneId: 4 });
  const result = await client.event("sync.snapshot");

  assert.equal(result.payload.snapshot.scene.id, 4);
  assert.equal(result.payload.snapshot.fog.userId, 1);
  assert.equal(result.payload.snapshotError, undefined);
  await client.close();
});

test("keeps the connection usable when the background snapshot is unavailable", async () => {
  const snapshotBackend = {
    get: async () => {
      const error = new Error("snapshot_unavailable");
      error.code = "snapshot_unavailable";
      error.status = 503;
      throw error;
    },
  };
  const setup = await startTestServer({}, { snapshotBackend });
  running.push(setup);
  const client = await connectClient(setup.url);
  await authenticate(client, signTicket());

  client.send({ v: 1, type: "sync.request", lastSequence: 0, sceneId: 4 });
  const result = await client.event("sync.snapshot");

  assert.equal(result.payload.snapshot, undefined);
  assert.equal(result.payload.snapshotError, "snapshot_unavailable");
  await client.close();
});
