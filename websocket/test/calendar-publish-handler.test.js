import { createHmac } from "node:crypto";
import { EventEmitter } from "node:events";
import test from "node:test";
import assert from "node:assert/strict";
import {
  createCalendarPublishHandler,
  parseCalendarPublication,
  verifyCalendarPublishSignature,
} from "../src/calendar-publish-handler.js";

test("calendar publication accepts only authoritative event envelopes", () => {
  assert.deepEqual(
    parseCalendarPublication({
      type: "calendar.state.updated",
      campaignId: 7,
      revision: 4,
      actorUserId: 2,
      payload: { state: { year: 2522 } },
    }),
    {
      type: "calendar.state.updated",
      campaignId: 7,
      revision: 4,
      actorUserId: 2,
      payload: { state: { year: 2522 } },
    },
  );
  assert.throws(
    () =>
      parseCalendarPublication({
        type: "calendar.state.updated",
        campaignId: 0,
        revision: 4,
        actorUserId: 2,
        payload: {},
      }),
    /campaignId/,
  );
});

test("calendar publication signature binds timestamp and exact body", () => {
  const secret = "s".repeat(32);
  const timestamp = "1789862400";
  const body = '{"type":"calendar.event.deleted"}';
  const signature = createHmac("sha256", secret)
    .update(`${timestamp}.${body}`)
    .digest("hex");
  const now = () => 1789862400 * 1000;
  assert.equal(
    verifyCalendarPublishSignature({ body, timestamp, signature, secret, now }),
    true,
  );
  assert.equal(
    verifyCalendarPublishSignature({
      body: `${body} `,
      timestamp,
      signature,
      secret,
      now,
    }),
    false,
  );
});

test("calendar publication broadcasts the server-owned revision to every campaign session", async () => {
  const secret = "r".repeat(32);
  const publication = {
    type: "calendar.event.created",
    campaignId: 7,
    revision: 12,
    actorUserId: 2,
    payload: { eventId: 44 },
  };
  const body = JSON.stringify(publication);
  const timestamp = String(Math.floor(Date.now() / 1000));
  const signature = createHmac("sha256", secret)
    .update(`${timestamp}.${body}`)
    .digest("hex");
  const sent = [];
  const sessions = [1, 2].map(() => ({
    ws: { readyState: 1, send: (value) => sent.push(JSON.parse(value)) },
  }));
  let sequence = 0;
  const handler = createCalendarPublishHandler({
    config: {
      calendarPublishPath: "/internal/calendar",
      maxPayloadBytes: 4096,
      ticketSecret: secret,
    },
    rooms: {
      nextSequence: (campaignId) => {
        assert.equal(campaignId, 7);
        sequence += 1;
        return sequence;
      },
      sessions: (campaignId) => {
        assert.equal(campaignId, 7);
        return sessions;
      },
    },
  });
  const request = new EventEmitter();
  request.method = "POST";
  request.headers = {
    "x-realtime-timestamp": timestamp,
    "x-realtime-signature": signature,
  };
  const completed = new Promise((resolve) => {
    const response = {
      writeHead(status) {
        assert.equal(status, 202);
      },
      end(value) {
        assert.equal(JSON.parse(value).accepted, true);
        resolve();
      },
    };
    assert.equal(handler(request, response, "/internal/calendar"), true);
  });

  request.emit("data", Buffer.from(body));
  request.emit("end");
  await completed;

  assert.equal(sent.length, 2);
  for (const event of sent) {
    assert.equal(event.type, "calendar.event.created");
    assert.equal(event.campaignId, 7);
    assert.equal(event.payload.eventId, 44);
    assert.equal(event.payload.revision, 12);
  }
});
