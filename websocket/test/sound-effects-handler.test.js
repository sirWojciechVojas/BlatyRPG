import assert from "node:assert/strict";
import test from "node:test";
import { createSoundEffectsHandler } from "../src/sound-effects-handler.js";

const settle = () => new Promise((resolve) => setImmediate(resolve));

const session = (userId, campaignId, campaignRole = "player") => {
  const events = [];
  return {
    userId,
    campaignId,
    campaignRole,
    events,
    ws: {
      readyState: 1,
      send: (raw) => events.push(JSON.parse(raw)),
    },
  };
};

test("broadcasts play, stop and stop-all only inside the authenticated campaign room", async () => {
  const gm = session(1, 5, "gm");
  const player = session(2, 5);
  const outsider = session(3, 6);
  let sequence = 0;
  const backend = {
    state: async () => ({ activePlaybacks: [], revision: 1 }),
    command: async (actor, request) => ({
      ...request,
      campaignId: actor.campaignId,
      serverTime: 1760000000000,
      executeAt: 1760000000300,
      audio: { id: 9, url: "/api/audio/9", duration: 4 },
      volume: 0.7,
      loop: false,
      fadeInMs: 0,
      fadeOutMs: 120,
      audienceScope: "all",
      recipientUserIds: [],
    }),
  };
  const rooms = {
    sessions: (campaignId) =>
      [gm, player, outsider].filter((item) => item.campaignId === campaignId),
    nextSequence: () => ++sequence,
  };
  const handler = createSoundEffectsHandler({
    backend,
    rooms,
    onAuthenticationFailure: () => assert.fail("unexpected auth failure"),
  });

  for (const request of [
    {
      type: "SOUND_EFFECT_PLAY",
      requestId: "request-1",
      playbackId: "playback-1",
      slotId: 12,
    },
    {
      type: "SOUND_EFFECT_STOP",
      requestId: "request-2",
      playbackId: "playback-1",
    },
    {
      type: "SOUND_EFFECT_STOP_ALL",
      requestId: "request-3",
      fadeOutMs: 120,
    },
  ]) {
    handler.handle(gm, request);
  }
  await settle();
  await settle();

  assert.deepEqual(
    gm.events.map((event) => event.type),
    ["SOUND_EFFECT_PLAY", "SOUND_EFFECT_STOP", "SOUND_EFFECT_STOP_ALL"],
  );
  assert.deepEqual(
    player.events.map((event) => event.type),
    ["SOUND_EFFECT_PLAY", "SOUND_EFFECT_STOP", "SOUND_EFFECT_STOP_ALL"],
  );
  assert.deepEqual(outsider.events, []);
  assert.ok(gm.events.every((event) => event.campaignId === 5));
});

test("filters selected and GM-only recipients without leaking an event", async () => {
  const gm = session(1, 5, "gm");
  const selected = session(2, 5);
  const omitted = session(3, 5);
  const rooms = {
    sessions: () => [gm, selected, omitted],
    nextSequence: () => 1,
  };
  let audienceScope = "selected";
  const handler = createSoundEffectsHandler({
    backend: {
      state: async () => ({}),
      command: async (_actor, request) => ({
        ...request,
        audienceScope,
        recipientUserIds: audienceScope === "selected" ? [2] : [],
      }),
    },
    rooms,
    onAuthenticationFailure: () => assert.fail("unexpected auth failure"),
  });

  handler.handle(gm, {
    type: "SOUND_EFFECT_PLAY",
    requestId: "selected-request",
    playbackId: "selected-playback",
    slotId: 1,
  });
  await settle();
  await settle();
  assert.equal(gm.events.length, 1);
  assert.equal(selected.events.length, 1);
  assert.equal(omitted.events.length, 0);

  audienceScope = "gm";
  handler.handle(gm, {
    type: "SOUND_EFFECT_PLAY",
    requestId: "gm-request",
    playbackId: "gm-playback",
    slotId: 2,
  });
  await settle();
  await settle();
  assert.equal(gm.events.length, 2);
  assert.equal(selected.events.length, 1);
  assert.equal(omitted.events.length, 0);
});

test("rejects control commands from a player before calling the backend", async () => {
  const player = session(2, 5);
  let calls = 0;
  const handler = createSoundEffectsHandler({
    backend: {
      state: async () => ({}),
      command: async () => {
        calls += 1;
        return {};
      },
    },
    rooms: { sessions: () => [player], nextSequence: () => 1 },
    onAuthenticationFailure: () => assert.fail("unexpected auth failure"),
  });

  handler.handle(player, {
    type: "SOUND_EFFECT_STOP_ALL",
    requestId: "forbidden-request",
    fadeOutMs: 0,
  });
  await settle();

  assert.equal(calls, 0);
  assert.equal(player.events[0].type, "SOUND_EFFECT_ERROR");
  assert.deepEqual(player.events[0].payload, {
    requestId: "forbidden-request",
    code: "sound_effect_forbidden",
    status: 403,
  });
});

test("sends reconnect state only to the reconnecting campaign session", async () => {
  const player = session(2, 5);
  const handler = createSoundEffectsHandler({
    backend: {
      state: async (actor) => ({
        activePlaybacks: [
          {
            playbackId: "loop-playback",
            campaignId: actor.campaignId,
            startedAt: 1759999990000,
            loop: true,
          },
        ],
        revision: 8,
        serverTime: 1760000000000,
      }),
    },
    rooms: { sessions: () => [], nextSequence: () => 1 },
    onAuthenticationFailure: () => assert.fail("unexpected auth failure"),
  });

  await handler.sendState(player, "sync-request");

  assert.equal(player.events.length, 1);
  assert.equal(player.events[0].type, "SOUND_EFFECT_STATE");
  assert.equal(player.events[0].payload.activePlaybacks[0].loop, true);
  assert.equal(player.events[0].payload.requestId, "sync-request");
});
