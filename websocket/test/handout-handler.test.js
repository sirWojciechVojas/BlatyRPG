import assert from "node:assert/strict";
import test from "node:test";
import { createHandoutHandler } from "../src/handout-handler.js";

const session = (id, userId, sent) => ({
  id,
  userId,
  campaignId: 7,
  ws: {
    readyState: 1,
    send: (raw) => sent.push(JSON.parse(raw)),
  },
});

test("delivers only to backend-confirmed online recipients", async () => {
  const senderEvents = [];
  const selectedEvents = [];
  const deniedEvents = [];
  const sender = session("sender", 1, senderEvents);
  const selected = session("selected", 2, selectedEvents);
  const denied = session("denied", 3, deniedEvents);
  const handler = createHandoutHandler({
    backend: {
      deliveryTargets: async () => [
        {
          userId: 2,
          notificationId: 17,
          handoutId: 9,
          title: "Vault map",
        },
      ],
    },
    rooms: { sessions: () => [sender, selected, denied] },
    onAuthenticationFailure: () => assert.fail("unexpected authentication failure"),
  });

  handler.handle(sender, {
    requestId: "handout-1",
    batchId: "8f1c0c29-29cd-4e5e-9d3a-e8e9d2b2b8aa",
  });
  await new Promise((resolve) => setImmediate(resolve));

  assert.deepEqual(
    selectedEvents.map((event) => event.type),
    ["handout.available"],
  );
  assert.deepEqual(selectedEvents[0].payload, {
    notificationId: 17,
    handoutId: 9,
    title: "Vault map",
  });
  assert.deepEqual(deniedEvents, []);
  assert.equal(senderEvents.at(-1).type, "handout.ack");
});
