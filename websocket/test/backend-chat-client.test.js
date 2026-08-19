import assert from "node:assert/strict";
import test from "node:test";
import { BackendChatClient, BackendChatError } from "../src/backend-chat-client.js";
import { testConfig } from "./helpers.js";

const session = {
  campaignId: 7,
  clientInstanceId: "client-instance-0001",
  realtimeTicket: "secret-ticket",
};

test("forwards only authoritative session scope to the internal backend", async () => {
  let call;
  const client = new BackendChatClient(testConfig(), {
    fetch: async (url, options) => {
      call = { url, options };
      return new Response(JSON.stringify({
        message: {
          id: 4,
          revision: 4,
          campaignId: 7,
          type: "text",
          body: "Hello",
          author: { id: 2, name: "Ada" },
        },
        capabilities: { canRead: true, canSend: true },
        duplicate: false,
      }), { status: 201 });
    },
  });

  const result = await client.send(session, {
    body: "Hello",
    clientNonce: "550e8400-e29b-41d4-a716-446655440000",
  });

  assert.equal(call.url, "http://backend.internal/api/internal/realtime/campaigns/7/chat/send");
  assert.equal(call.options.headers.Authorization, "Realtime secret-ticket");
  assert.equal(call.options.headers["X-Realtime-Client-Instance"], session.clientInstanceId);
  assert.deepEqual(JSON.parse(call.options.body), {
    body: "Hello",
    clientNonce: "550e8400-e29b-41d4-a716-446655440000",
  });
  assert.equal(result.message.author.isCurrentUser, undefined);
});

test("keeps committed history when its author account was deleted", async () => {
  const client = new BackendChatClient(testConfig(), {
    fetch: async () => new Response(JSON.stringify({
      items: [{
        id: 5,
        revision: 5,
        campaignId: 7,
        type: "text",
        body: "Archived",
        author: { id: null, name: "Deleted user" },
      }],
      pagination: { hasMoreBefore: false, hasMoreAfter: false },
      capabilities: { canRead: true, canSend: true },
      sync: { latestRevision: 5 },
    }), { status: 200 }),
  });

  const result = await client.sync(session);
  assert.equal(result.items[0].author.id, null);
});

test("fails closed on a malformed or rejected backend response", async () => {
  const malformed = new BackendChatClient(testConfig(), {
    fetch: async () => new Response("not-json", { status: 200 }),
  });
  await assert.rejects(
    malformed.sync(session),
    (error) => error instanceof BackendChatError && error.code === "backend_response_invalid",
  );

  const denied = new BackendChatClient(testConfig(), {
    fetch: async () => new Response(
      JSON.stringify({ code: "forbidden" }),
      { status: 403 },
    ),
  });
  await assert.rejects(
    denied.send(session, { body: "x", clientNonce: "nonce" }),
    (error) => error instanceof BackendChatError && error.status === 403,
  );
});
