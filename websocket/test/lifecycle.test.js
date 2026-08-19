import assert from "node:assert/strict";
import { afterEach, test } from "node:test";
import {
  authenticate,
  connectClient,
  signTicket,
  startTestServer,
} from "./helpers.js";

const running = [];
afterEach(async () => Promise.all(running.splice(0).map(({ server }) => server.stop())));

const boot = async (overrides) => {
  const setup = await startTestServer(overrides);
  running.push(setup);
  return setup;
};

test("closes unauthenticated sockets after the handshake deadline", async () => {
  const { url } = await boot({ authTimeoutMs: 40, heartbeatIntervalMs: 1000 });
  const client = await connectClient(url);
  const error = await client.event("protocol.error");
  const closed = await client.waitForClose();
  assert.equal(error.payload.code, "auth_timeout");
  assert.equal(closed.code, 4001);
});

test("marks a ticket expired before authentication as recoverable", async () => {
  const { url } = await boot();
  const now = Math.floor(Date.now() / 1000);
  const client = await connectClient(url);
  client.send({
    v: 1,
    type: "auth",
    ticket: signTicket({ iat: now - 10, exp: now - 1 }),
    clientInstanceId: "client-instance-0001",
  });

  const error = await client.event("protocol.error");
  const closed = await client.waitForClose();
  assert.equal(error.payload.code, "ticket_expired");
  assert.deepEqual(closed, { code: 4001, reason: "auth_expired" });
});

test("closes an authenticated socket exactly when its ticket expires", async () => {
  const { url } = await boot({ heartbeatIntervalMs: 1000 });
  const now = Math.floor(Date.now() / 1000);
  const client = await connectClient(url);
  await authenticate(client, signTicket({ iat: now, exp: now + 1 }));
  const error = await client.event("protocol.error", 1800);
  const closed = await client.waitForClose(1800);
  assert.equal(error.payload.code, "ticket_expired");
  assert.equal(closed.code, 4001);
});

test("enforces the message rate limit after authentication", async () => {
  const { url } = await boot({
    rateLimitMessages: 2,
    rateLimitWindowMs: 1000,
    heartbeatIntervalMs: 1000,
  });
  const client = await connectClient(url);
  const { snapshot } = await authenticate(client, signTicket());
  client.send({
    v: 1,
    type: "sync.request",
    lastSequence: snapshot.payload.latestSequence,
  });
  await client.event("sync.snapshot");
  client.send({ v: 1, type: "sync.request", lastSequence: 0 });
  const error = await client.event("protocol.error");
  const closed = await client.waitForClose();
  assert.equal(error.payload.code, "rate_limited");
  assert.equal(closed.code, 4008);
});

test("terminates a connection that does not answer heartbeat pings", async () => {
  const { url } = await boot({ heartbeatIntervalMs: 30 });
  const client = await connectClient(url, { autoPong: false });
  await authenticate(client, signTicket());
  const closed = await client.waitForClose(300);
  assert.equal(closed.code, 1006);
});

test("rejects payloads above the configured maximum", async () => {
  const { url } = await boot({ maxPayloadBytes: 1024, heartbeatIntervalMs: 1000 });
  const client = await connectClient(url);
  await authenticate(client, signTicket());
  client.send("x".repeat(2048));
  const closed = await client.waitForClose();
  assert.equal(closed.code, 1009);
});
