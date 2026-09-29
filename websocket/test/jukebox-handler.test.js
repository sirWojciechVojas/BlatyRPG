import assert from "node:assert/strict";
import test from "node:test";
import { createJukeboxHandler } from "../src/jukebox-handler.js";

const settle = () => new Promise((resolve) => setImmediate(resolve));

test("replaces device metadata when a library track starts on the channel", async () => {
  const events = [];
  let revision = 0;
  const session = {
    userId: 2,
    campaignId: 5,
    campaignRole: "gm",
    ws: {
      readyState: 1,
      send: (raw) => events.push(JSON.parse(raw)),
    },
  };
  const handler = createJukeboxHandler({
    backend: {
      state: async () => ({ state: {}, settings: {}, revision }),
      save: async (_session, state, settings) => ({
        state,
        settings,
        revision: ++revision,
      }),
    },
    rooms: {
      sessions: () => [session],
      nextSequence: () => revision + 1,
    },
    onAuthenticationFailure: () =>
      assert.fail("unexpected authentication failure"),
  });

  handler.handle(session, {
    type: "JUKEBOX_DEVICE",
    requestId: "device-1",
    channelId: "music",
    deviceLabel: "Voicemeeter AUX",
    deviceSlot: "external-1",
    volume: 0.7,
    muted: false,
  });
  await settle();
  await settle();

  handler.handle(session, {
    type: "JUKEBOX_PLAY",
    requestId: "play-1",
    channelId: "music",
    trackId: 42,
    position: 0,
    duration: 120,
    loop: false,
    volume: 0.6,
    muted: false,
    sourceType: "upload",
    playlistId: null,
    sourceLabel: "",
  });
  await settle();
  await settle();

  assert.deepEqual(
    events.map((event) => event.type),
    ["JUKEBOX_DEVICE", "JUKEBOX_PLAY"],
  );
  assert.deepEqual(
    {
      trackId: events[1].payload.trackId,
      sourceType: events[1].payload.sourceType,
      deviceLabel: events[1].payload.deviceLabel,
      deviceSlot: events[1].payload.deviceSlot,
    },
    {
      trackId: 42,
      sourceType: "upload",
      deviceLabel: "",
      deviceSlot: null,
    },
  );
});
