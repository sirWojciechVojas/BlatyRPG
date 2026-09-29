import { createHmac } from "node:crypto";
import { EventEmitter } from "node:events";
import assert from "node:assert/strict";
import test from "node:test";
import {
  CHARACTER_ACCESS_EVENT_TYPE,
  createCharacterAccessPublishHandler,
  parseCharacterAccessPublication,
  verifyCharacterAccessPublishSignature,
} from "../src/character-access-publish-handler.js";

test("character access publication accepts only its minimal invalidation envelope", () => {
  assert.deepEqual(
    parseCharacterAccessPublication({
      type: CHARACTER_ACCESS_EVENT_TYPE,
      campaignId: 7,
      characterId: 12,
      actorUserId: 2,
    }),
    {
      type: CHARACTER_ACCESS_EVENT_TYPE,
      campaignId: 7,
      characterId: 12,
      actorUserId: 2,
    },
  );
  assert.throws(
    () =>
      parseCharacterAccessPublication({
        type: CHARACTER_ACCESS_EVENT_TYPE,
        campaignId: 7,
        characterId: 12,
        actorUserId: 2,
        userId: 9,
      }),
    /unexpected_field/,
  );
});

test("character access publication signature binds timestamp and exact body", () => {
  const secret = "s".repeat(32);
  const timestamp = "1789862400";
  const body = '{"type":"character.access.changed"}';
  const signature = createHmac("sha256", secret)
    .update(`${timestamp}.${body}`)
    .digest("hex");
  const now = () => 1789862400 * 1000;

  assert.equal(
    verifyCharacterAccessPublishSignature({
      body,
      timestamp,
      signature,
      secret,
      now,
    }),
    true,
  );
  assert.equal(
    verifyCharacterAccessPublishSignature({
      body: `${body} `,
      timestamp,
      signature,
      secret,
      now,
    }),
    false,
  );
});

test("character access publication broadcasts a data-minimal refresh event", async () => {
  const secret = "r".repeat(32);
  const publication = {
    type: CHARACTER_ACCESS_EVENT_TYPE,
    campaignId: 7,
    characterId: 12,
    actorUserId: 2,
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
  const handler = createCharacterAccessPublishHandler({
    config: {
      characterAccessPublishPath: "/internal/character-access",
      maxPayloadBytes: 4096,
      ticketSecret: secret,
    },
    rooms: {
      nextSequence: (campaignId) => {
        assert.equal(campaignId, 7);
        return 4;
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
    assert.equal(handler(request, response, "/internal/character-access"), true);
  });

  request.emit("data", Buffer.from(body));
  request.emit("end");
  await completed;

  assert.equal(sent.length, 2);
  for (const event of sent) {
    assert.equal(event.type, CHARACTER_ACCESS_EVENT_TYPE);
    assert.equal(event.campaignId, 7);
    assert.equal(event.payload.characterId, 12);
    assert.deepEqual(Object.keys(event.payload), ["characterId"]);
  }
});
