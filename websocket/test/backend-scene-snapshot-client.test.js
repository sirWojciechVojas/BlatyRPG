import assert from "node:assert/strict";
import test from "node:test";
import {
  BackendSceneSnapshotClient,
  BackendSceneSnapshotError,
} from "../src/backend-scene-snapshot-client.js";
import { testConfig } from "./helpers.js";

const session = {
  campaignId: 7,
  realtimeTicket: "signed-ticket",
  clientInstanceId: "client-instance-0001",
};

const snapshot = {
  scenes: {
    items: [{ id: 4, campaignId: 7 }],
    activeSceneId: 4,
    capabilities: {},
  },
  scene: { id: 4, campaignId: 7 },
  capabilities: {},
  tokens: { items: [], capabilities: {} },
  walls: { items: [], capabilities: {} },
  lights: { items: [], capabilities: {} },
  tiles: { items: [], capabilities: {} },
  combat: { combat: null, capabilities: {} },
  fog: { sceneId: 4, userId: 1 },
  movementRequests: { items: [], capabilities: {} },
};

const response = (body, status = 200) => ({
  ok: status >= 200 && status < 300,
  status,
  text: async () => JSON.stringify(body),
});

test("requests an authoritative scoped scene snapshot", async () => {
  const fetch = async (url, options) => {
    assert.equal(
      url,
      "http://backend.internal/api/internal/realtime/campaigns/7/scenes/4/snapshot",
    );
    assert.equal(options.method, "POST");
    assert.equal(options.headers.Authorization, "Realtime signed-ticket");
    assert.equal(
      options.headers["X-Realtime-Client-Instance"],
      "client-instance-0001",
    );
    return response({ snapshot });
  };
  const client = new BackendSceneSnapshotClient(testConfig(), { fetch });

  assert.deepEqual(await client.get(session, 4), snapshot);
});

test("fails closed when the backend returns an incomplete snapshot", async () => {
  const client = new BackendSceneSnapshotClient(testConfig(), {
    fetch: async () => response({ snapshot: { scene: { id: 4 } } }),
  });

  await assert.rejects(
    client.get(session, 4),
    (error) =>
      error instanceof BackendSceneSnapshotError &&
      error.code === "backend_response_invalid",
  );
});
