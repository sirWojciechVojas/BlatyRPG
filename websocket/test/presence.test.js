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

afterEach(async () => {
  await Promise.all(running.splice(0).map(({ server }) => server.stop()));
});

const boot = async (overrides = {}) => {
  const setup = await startTestServer({ heartbeatIntervalMs: 10000, ...overrides });
  running.push(setup);
  return setup;
};

const login = async (url, claims, clientInstanceId) => {
  const client = await connectClient(url);
  const result = await authenticate(
    client,
    signTicket({ ...claims, client_instance_id: clientInstanceId }),
    { clientInstanceId },
  );
  return { client, ...result };
};

test("isolates campaign rooms and snapshots only their presence", async () => {
  const { url } = await boot();
  const alice = await login(
    url,
    { sub: 1, campaign_id: 7, display_name: "Alice" },
    "alice-browser-01",
  );
  const bob = await login(
    url,
    { sub: 2, campaign_id: 7, display_name: "Bob" },
    "bob-browser-0001",
  );
  const bobJoined = await alice.client.waitFor(
    (event) => event.type === "presence.changed" && event.payload.user.userId === 2,
  );
  assert.equal(bobJoined.payload.change, "connected");
  assert.deepEqual(
    bob.snapshot.payload.users.map(({ userId }) => userId),
    [1, 2],
  );

  const charlie = await login(
    url,
    { sub: 3, campaign_id: 8, display_name: "Charlie" },
    "charlie-browser1",
  );
  assert.deepEqual(
    charlie.snapshot.payload.users.map(({ userId }) => userId),
    [3],
  );
  await delay(30);
  assert.equal(alice.client.history.some((event) => event.campaignId === 8), false);
  assert.equal(bob.client.history.some((event) => event.campaignId === 8), false);

  await Promise.all([alice.client.close(), bob.client.close(), charlie.client.close()]);
});

test("counts multiple user connections and atomically replaces one client instance", async () => {
  const { url } = await boot({ offlineGraceMs: 60 });
  const observer = await login(
    url,
    { sub: 9, campaign_id: 7, display_name: "GM", campaign_role: "gm" },
    "observer-browser1",
  );
  const first = await login(
    url,
    { sub: 1, campaign_id: 7, display_name: "Alice" },
    "alice-browser-01",
  );
  await observer.client.waitFor(
    (event) => event.type === "presence.changed" && event.payload.user.userId === 1,
  );
  const second = await login(
    url,
    { sub: 1, campaign_id: 7, display_name: "Alice" },
    "alice-browser-02",
  );
  const added = await observer.client.waitFor(
    (event) =>
      event.type === "presence.changed" && event.payload.change === "connection_added",
  );
  assert.equal(added.payload.user.connectionCount, 2);

  const replacement = await login(
    url,
    { sub: 1, campaign_id: 7, display_name: "Alice" },
    "alice-browser-01",
  );
  const replacedEvent = await observer.client.waitFor(
    (event) => event.type === "presence.changed" && event.payload.change === "reconnected",
  );
  assert.equal(replacedEvent.payload.user.connectionCount, 2);
  assert.equal((await first.client.waitForClose()).code, 4010);
  assert.equal(
    replacement.snapshot.payload.users.find(({ userId }) => userId === 1).connectionCount,
    2,
  );

  await second.client.close();
  const removed = await observer.client.waitFor(
    (event) => event.type === "presence.changed" && event.payload.change === "connection_removed",
  );
  assert.equal(removed.payload.user.connectionCount, 1);

  await replacement.client.close();
  const reconnecting = await observer.client.waitFor(
    (event) => event.type === "presence.changed" && event.payload.change === "reconnecting",
  );
  assert.equal(reconnecting.payload.user.online, true);
  assert.equal(reconnecting.payload.user.connectionCount, 0);
  const offline = await observer.client.waitFor(
    (event) => event.type === "presence.changed" && event.payload.change === "disconnected",
  );
  assert.equal(offline.payload.user.online, false);
  await observer.client.close();
});

test("reconnects inside the grace period without emitting offline", async () => {
  const { url } = await boot({ offlineGraceMs: 120 });
  const observer = await login(
    url,
    { sub: 9, campaign_id: 7, display_name: "GM" },
    "observer-browser1",
  );
  const first = await login(
    url,
    { sub: 1, campaign_id: 7, display_name: "Alice" },
    "alice-stable-id1",
  );
  await observer.client.waitFor(
    (event) => event.type === "presence.changed" && event.payload.user.userId === 1,
  );
  await first.client.close();
  await observer.client.waitFor(
    (event) => event.type === "presence.changed" && event.payload.change === "reconnecting",
  );
  const reconnected = await login(
    url,
    { sub: 1, campaign_id: 7, display_name: "Alice" },
    "alice-stable-id1",
  );
  const change = await observer.client.waitFor(
    (event) => event.type === "presence.changed" && event.payload.change === "reconnected",
  );
  assert.equal(change.payload.user.connectionCount, 1);
  await delay(160);
  assert.equal(
    observer.client.history.some(
      (event) =>
        event.type === "presence.changed" &&
        event.payload.change === "disconnected" &&
        event.payload.user.userId === 1,
    ),
    false,
  );
  await Promise.all([reconnected.client.close(), observer.client.close()]);
});
