import assert from "node:assert/strict";
import test from "node:test";
import { BackendTokenClient, BackendTokenError } from "../src/backend-token-client.js";
import { testConfig } from "./helpers.js";

const session = {
  campaignId: 7,
  clientInstanceId: "client-instance-0001",
  realtimeTicket: "secret-ticket",
};

test("forwards token moves using authoritative session scope", async () => {
  let call;
  const client = new BackendTokenClient(testConfig(), {
    fetch: async (url, options) => {
      call = { url, options };
      return new Response(JSON.stringify({
        token: {
          id: 9,
          sceneId: 4,
          name: "Guard",
          x: 30,
          y: 40,
          rotation: 90,
          movementRange: 8,
          movementSpent: 3,
          movementPoints: 5,
          visibleTo: { mode: "users", userIds: [2, "4", 2] },
          revision: 4,
        },
        visibility: { publishToPlayers: true },
      }), { status: 200 });
    },
  });

  const result = await client.move(session, {
    sceneId: 4, tokenId: 9, revision: 3, x: 30, y: 40,
    waypoints: [{ x: 20, y: 30 }],
  });
  assert.equal(call.url, "http://backend.internal/api/internal/realtime/campaigns/7/tokens/move");
  assert.equal(call.options.headers.Authorization, "Realtime secret-ticket");
  assert.deepEqual(JSON.parse(call.options.body), {
    sceneId: 4, tokenId: 9, revision: 3, x: 30, y: 40,
    waypoints: [{ x: 20, y: 30 }],
  });
  assert.equal(result.token.revision, 4);
  assert.equal(result.token.facing, 90);
  assert.equal(result.token.movementPoints, 5);
  assert.deepEqual(result.token.visibleTo, { mode: "users", userIds: [2, 4] });
  assert.equal(result.publishToPlayers, true);
});

test("fails closed on malformed committed tokens", async () => {
  const client = new BackendTokenClient(testConfig(), {
    fetch: async () => new Response(JSON.stringify({ token: { id: 9 } }), { status: 200 }),
  });
  await assert.rejects(
    client.move(session, { sceneId: 4, tokenId: 9, revision: 3, x: 1, y: 2 }),
    (error) => error instanceof BackendTokenError && error.status === 502,
  );
});

test("forwards an atomic token group move to one backend endpoint", async () => {
  let call;
  const committed = [
    { id: 9, sceneId: 4, name: "A", x: 100, y: 200, revision: 4 },
    { id: 10, sceneId: 4, name: "B", x: 300, y: 400, revision: 8 },
  ];
  const client = new BackendTokenClient(testConfig(), {
    fetch: async (url, options) => {
      call = { url, body: JSON.parse(options.body) };
      return new Response(
        JSON.stringify({
          items: committed.map((token) => ({
            token,
            visibility: { publishToPlayers: true },
          })),
        }),
        { status: 200 },
      );
    },
  });
  const moves = [
    { tokenId: 9, revision: 3, x: 100, y: 200, waypoints: [] },
    { tokenId: 10, revision: 7, x: 300, y: 400, waypoints: [] },
  ];

  const result = await client.moveGroup(session, { sceneId: 4, moves });

  assert.match(call.url, /campaigns\/7\/tokens\/move-group$/);
  assert.deepEqual(call.body, { sceneId: 4, moves });
  assert.deepEqual(
    result.items.map(({ token }) => token.id),
    [9, 10],
  );
});

test("forwards angle changes through the authoritative token endpoint", async () => {
  let call;
  const client = new BackendTokenClient(testConfig(), {
    fetch: async (url, options) => {
      call = { url, body: JSON.parse(options.body) };
      return new Response(JSON.stringify({
        token: {
          id: 9, sceneId: 4, name: "Guard", x: 30, y: 40,
          rotation: 72.5, facing: 185, revision: 4,
        },
        visibility: { publishToPlayers: true },
      }), { status: 200 });
    },
  });

  const result = await client.change(session, {
    sceneId: 4,
    tokenId: 9,
    revision: 3,
    changes: { rotation: 72.5, facing: 185 },
  });

  assert.match(call.url, /campaigns\/7\/tokens\/change$/);
  assert.deepEqual(call.body, {
    sceneId: 4,
    tokenId: 9,
    revision: 3,
    changes: { rotation: 72.5, facing: 185 },
  });
  assert.equal(result.token.rotation, 72.5);
  assert.equal(result.token.facing, 185);
  assert.equal(result.publishToPlayers, true);
});

test("creates and resolves authoritative movement requests", async () => {
  const calls = [];
  const movement = {
    id: 31,
    sceneId: 4,
    tokenId: 9,
    requestedByUserId: 2,
    tokenName: "Guard",
    requesterName: "Player",
    cost: 8,
    spent: 2,
    range: 6,
    status: "pending",
  };
  const client = new BackendTokenClient(testConfig(), {
    fetch: async (url, options) => {
      calls.push({ url, body: JSON.parse(options.body) });
      return new Response(
        JSON.stringify(
          calls.length === 1
            ? { request: movement }
            : {
                request: { ...movement, status: "approved" },
                token: {
                  id: 9, sceneId: 4, name: "Guard", x: 500, y: 600,
                  movementSpent: 10, revision: 4,
                },
                visibility: { publishToPlayers: true },
              },
        ),
        { status: calls.length === 1 ? 201 : 200 },
      );
    },
  });

  const requested = await client.requestMovement(session, {
    sceneId: 4, tokenId: 9, revision: 3, x: 500, y: 600, waypoints: [],
  });
  const resolved = await client.resolveMovement(session, {
    movementRequestId: 31, decision: "approve",
  });

  assert.equal(requested.request.cost, 8);
  assert.match(calls[0].url, /tokens\/movement-requests$/);
  assert.match(calls[1].url, /tokens\/movement-requests\/31\/resolve$/);
  assert.deepEqual(calls[1].body, { decision: "approve" });
  assert.equal(resolved.token.movementSpent, 10);
});
