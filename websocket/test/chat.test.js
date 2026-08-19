import assert from "node:assert/strict";
import { afterEach, test } from "node:test";
import { BackendChatError } from "../src/backend-chat-client.js";
import {
  authenticate,
  connectClient,
  delay,
  signTicket,
  startTestServer,
} from "./helpers.js";

const running = [];
afterEach(async () => Promise.all(running.splice(0).map(({ server }) => server.stop())));

const committed = {
  id: 12,
  revision: 12,
  campaignId: 7,
  type: "text",
  body: "Ready",
  author: { id: 1, name: "Alice" },
  clientNonce: "550e8400-e29b-41d4-a716-446655440000",
  metadata: null,
  createdAt: "2026-08-19 12:00:00",
};

const boot = async (chatBackend) => {
  const setup = await startTestServer({}, { chatBackend });
  running.push(setup);
  return setup;
};

const connect = async (url, userId, instance) => {
  const client = await connectClient(url);
  await authenticate(
    client,
    signTicket({
      sub: userId,
      auth_session_id: 100 + userId,
      client_instance_id: instance,
    }),
    { clientInstanceId: instance },
  );
  return client;
};

test("broadcasts only a committed message and acknowledges its nonce", async () => {
  let resolveCommit;
  const pending = new Promise((resolve) => {
    resolveCommit = resolve;
  });
  const backend = {
    send: async () => pending,
    sync: async () => ({
      items: [committed],
      pagination: { hasMoreBefore: false, hasMoreAfter: false },
      capabilities: { canRead: true, canSend: true, canModerate: false },
      latestRevision: 12,
    }),
  };
  const { url } = await boot(backend);
  const sender = await connect(url, 1, "client-instance-0001");
  const recipient = await connect(url, 2, "client-instance-0002");

  sender.send({
    v: 1,
    type: "chat.send",
    requestId: committed.clientNonce,
    clientNonce: committed.clientNonce,
    body: committed.body,
  });
  await delay(20);
  assert.equal(sender.history.some((event) => event.type === "chat.message"), false);
  assert.equal(recipient.history.some((event) => event.type === "chat.message"), false);

  resolveCommit({
    message: committed,
    capabilities: { canRead: true, canSend: true, canModerate: false },
    duplicate: false,
  });
  const [ownMessage, remoteMessage, ack] = await Promise.all([
    sender.event("chat.message"),
    recipient.event("chat.message"),
    sender.event("chat.ack"),
  ]);
  assert.deepEqual(ownMessage.payload.message, committed);
  assert.deepEqual(remoteMessage.payload.message, committed);
  assert.equal(ack.payload.clientNonce, committed.clientNonce);
  assert.equal(ownMessage.sequence, null);
});

test("serializes campaign writes and broadcasts in durable revision order", async () => {
  let releaseFirst;
  const firstGate = new Promise((resolve) => {
    releaseFirst = resolve;
  });
  const second = {
    ...committed,
    id: 13,
    revision: 13,
    body: "Second",
    clientNonce: "550e8400-e29b-41d4-a716-446655440001",
  };
  const calls = [];
  let active = 0;
  let maxActive = 0;
  const backend = {
    send: async (_session, request) => {
      calls.push(request.body);
      active += 1;
      maxActive = Math.max(maxActive, active);
      if (request.body === committed.body) await firstGate;
      active -= 1;
      return {
        message: request.body === committed.body ? committed : second,
        capabilities: { canRead: true, canSend: true },
        duplicate: false,
      };
    },
    sync: async () => ({
      items: [committed, second],
      pagination: { hasMoreBefore: false, hasMoreAfter: false },
      capabilities: { canRead: true, canSend: true },
      latestRevision: 13,
    }),
  };
  const { url } = await boot(backend);
  const sender = await connect(url, 1, "client-instance-0001");
  const recipient = await connect(url, 2, "client-instance-0002");

  sender.send({
    v: 1,
    type: "chat.send",
    requestId: committed.clientNonce,
    clientNonce: committed.clientNonce,
    body: committed.body,
  });
  sender.send({
    v: 1,
    type: "chat.send",
    requestId: second.clientNonce,
    clientNonce: second.clientNonce,
    body: second.body,
  });
  await delay(20);
  assert.deepEqual(calls, [committed.body]);
  releaseFirst();

  await Promise.all([sender.event("chat.ack"), sender.event("chat.ack")]);
  const firstRemote = await recipient.event("chat.message");
  const secondRemote = await recipient.event("chat.message");
  assert.deepEqual(
    [firstRemote.payload.message.revision, secondRemote.payload.message.revision],
    [12, 13],
  );
  assert.equal(maxActive, 1);
});

test("rechecks read access per recipient and keeps snapshots scoped", async () => {
  const backend = {
    send: async () => ({
      message: committed,
      capabilities: { canRead: true, canSend: true },
      duplicate: false,
    }),
    sync: async (session) => {
      if (session.userId === 2) throw new BackendChatError("forbidden", 403);
      return {
        items: [committed],
        pagination: { hasMoreBefore: false, hasMoreAfter: false },
        capabilities: { canRead: true, canSend: true },
        latestRevision: 12,
      };
    },
  };
  const { url } = await boot(backend);
  const sender = await connect(url, 1, "client-instance-0001");
  const denied = await connect(url, 2, "client-instance-0002");

  sender.send({
    v: 1,
    type: "chat.send",
    requestId: committed.clientNonce,
    clientNonce: committed.clientNonce,
    body: committed.body,
  });
  await sender.event("chat.ack");
  await delay(30);
  assert.equal(denied.history.some((event) => event.type === "chat.message"), false);

  sender.send({ v: 1, type: "chat.sync", requestId: "sync-1", afterRevision: 2 });
  const snapshot = await sender.event("chat.snapshot");
  assert.equal(snapshot.payload.mode, "missing");
  assert.equal(snapshot.payload.latestRevision, 12);
});

test("rejects client identity fields before calling the backend", async () => {
  let calls = 0;
  const backend = {
    send: async () => {
      calls += 1;
      throw new Error("unexpected");
    },
    sync: async () => ({ items: [], pagination: {}, capabilities: {}, latestRevision: 0 }),
  };
  const { url } = await boot(backend);
  const client = await connect(url, 1, "client-instance-0001");
  client.send({
    v: 1,
    type: "chat.send",
    requestId: committed.clientNonce,
    clientNonce: committed.clientNonce,
    body: "Ready",
    campaignId: 99,
  });
  const rejected = await client.event("protocol.error");
  assert.equal(rejected.payload.code, "unexpected_field");
  assert.equal(calls, 0);
});
