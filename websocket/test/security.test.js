import assert from "node:assert/strict";
import { afterEach, test } from "node:test";
import { WebSocket } from "ws";
import {
  TEST_ORIGIN,
  authenticate,
  connectClient,
  signTicket,
  startTestServer,
} from "./helpers.js";

const running = [];

afterEach(async () => {
  await Promise.all(running.splice(0).map(({ server }) => server.stop()));
});

const boot = async (overrides) => {
  const setup = await startTestServer(overrides);
  running.push(setup);
  return setup;
};

test("rejects disallowed and missing origins before upgrading", async () => {
  const { url } = await boot();
  const rejected = (options, target = url) =>
    new Promise((resolve, reject) => {
      const ws = new WebSocket(target, options);
      ws.on("unexpected-response", (_request, response) => {
        response.resume();
        resolve(response.statusCode);
      });
      ws.on("open", () => reject(new Error("origin unexpectedly accepted")));
      ws.on("error", () => {});
    });

  assert.equal(await rejected({ origin: "https://evil.test" }), 403);
  assert.equal(await rejected({}), 403);
  assert.equal(await rejected({ origin: TEST_ORIGIN }, `${url}?ticket=must-not-leak`), 400);
});

test("requires auth as the first message", async () => {
  const { url } = await boot();
  const client = await connectClient(url);
  client.send({ v: 1, type: "sync.request", lastSequence: 0 });
  const error = await client.event("protocol.error");
  const closed = await client.waitForClose();
  assert.equal(error.payload.code, "auth_first_message_required");
  assert.equal(closed.code, 4001);
});

test("authenticates a valid ticket and owns all envelope identity metadata", async () => {
  const { url } = await boot();
  const client = await connectClient(url);
  const { ready, snapshot } = await authenticate(
    client,
    signTicket({ sub: 12, campaign_id: 44, capabilities: { canManage: true } }),
    { requestId: "auth-1", lastSequence: 0 },
  );

  assert.equal(ready.campaignId, 44);
  assert.equal(ready.actorUserId, 12);
  assert.equal(ready.payload.requestId, "auth-1");
  assert.equal(ready.payload.capabilities.canManage, true);
  assert.equal(snapshot.campaignId, 44);
  assert.equal(snapshot.payload.resyncRequired, true);
  assert.match(ready.eventId, /^[0-9a-f-]{36}$/);
  assert.equal(new Date(ready.occurredAt).toISOString(), ready.occurredAt);

  client.send({
    v: 1,
    type: "sync.request",
    requestId: "spoof",
    lastSequence: snapshot.payload.latestSequence,
    campaignId: 999,
  });
  const rejected = await client.event("protocol.error");
  assert.equal(rejected.payload.code, "unexpected_field");

  client.send({
    v: 1,
    type: "sync.request",
    requestId: "sync-1",
    lastSequence: snapshot.payload.latestSequence,
  });
  const sync = await client.event("sync.snapshot");
  assert.equal(sync.campaignId, 44);
  assert.equal(sync.actorUserId, 12);
  assert.equal(sync.payload.requestId, "sync-1");
  assert.equal(sync.payload.resyncRequired, false);
  await client.close();
});

test("consumes each ticket jti only once", async () => {
  const { url } = await boot();
  const ticket = signTicket({
    sub: 2,
    campaign_id: 8,
    client_instance_id: "first-browser-instance",
  });
  const first = await connectClient(url);
  await authenticate(first, ticket, { clientInstanceId: "first-browser-instance" });

  const replay = await connectClient(url);
  replay.send({
    v: 1,
    type: "auth",
    ticket,
    clientInstanceId: "first-browser-instance",
  });
  const error = await replay.event("protocol.error");
  const closed = await replay.waitForClose();
  assert.equal(error.payload.code, "ticket_replayed");
  assert.equal(closed.code, 4001);
  await first.close();
});

test("exposes a non-cacheable health endpoint without identity data", async () => {
  const { url } = await boot();
  const endpoint = url.replace("ws://", "http://").replace("/realtime", "/health");
  const response = await fetch(endpoint);
  const payload = await response.json();
  assert.equal(response.status, 200);
  assert.equal(response.headers.get("cache-control"), "no-store");
  assert.deepEqual(payload, {
    ok: true,
    service: "blatyrpg-websocket",
    protocolVersion: 1,
    connections: 0,
  });
});
