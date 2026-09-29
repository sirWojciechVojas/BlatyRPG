import assert from "node:assert/strict";
import test from "node:test";
import {
  BackendLightClient,
  BackendLightError,
} from "../src/backend-light-client.js";
import { testConfig } from "./helpers.js";

const session = {
  campaignId: 7,
  clientInstanceId: "client-instance-0001",
  realtimeTicket: "secret-ticket",
};

test("forwards light writes using authoritative session scope", async () => {
  let call;
  const client = new BackendLightClient(testConfig(), {
    fetch: async (url, options) => {
      call = { url, options };
      return new Response(
        JSON.stringify({
          light: {
            id: 8,
            sceneId: 4,
            x: 100,
            y: 150,
            brightRadius: 200,
            dimRadius: 400,
            color: "#FFD27A",
            intensity: 0.8,
            clarity: 0.35,
            enabled: true,
            hidden: false,
            revision: 2,
          },
        }),
        { status: 200 },
      );
    },
  });

  const result = await client.change(session, {
    operation: "update",
    sceneId: 4,
    lightId: 8,
    revision: 1,
    changes: { enabled: false },
  });
  assert.equal(
    call.url,
    "http://backend.internal/api/internal/realtime/campaigns/7/lights/change",
  );
  assert.equal(call.options.headers.Authorization, "Realtime secret-ticket");
  assert.deepEqual(JSON.parse(call.options.body), {
    operation: "update",
    sceneId: 4,
    lightId: 8,
    revision: 1,
    changes: { enabled: false },
  });
  assert.equal(result.light.revision, 2);
  assert.equal(result.light.lumens, 640);
  assert.equal(result.light.clarity, 0.35);
});

test("fails closed on malformed committed lights", async () => {
  const client = new BackendLightClient(testConfig(), {
    fetch: async () =>
      new Response(JSON.stringify({ light: { id: 8 } }), { status: 200 }),
  });
  await assert.rejects(
    client.change(session, {
      operation: "update",
      sceneId: 4,
      lightId: 8,
      revision: 1,
      changes: { enabled: false },
    }),
    (error) => error instanceof BackendLightError && error.status === 502,
  );
});

test("reads an authoritative scene lighting snapshot", async () => {
  const client = new BackendLightClient(testConfig(), {
    fetch: async () =>
      new Response(
        JSON.stringify({
          scene: {
            id: 4,
            darkness_level: 0.8,
            global_illumination: true,
            fog_exploration: false,
            revision: 3,
          },
        }),
        { status: 200 },
      ),
  });
  const result = await client.change(session, {
    operation: "syncScene",
    sceneId: 4,
  });
  assert.deepEqual(result, {
    operation: "syncScene",
    scene: {
      id: 4,
      darkness_level: 0.8,
      global_illumination: true,
      fog_exploration: false,
      globalLightLevel: 0.856,
      darknessLevel: 0.14400000000000002,
      globalIllumination: true,
      fogExploration: false,
      isVisible: true,
      revision: 3,
    },
  });
});
