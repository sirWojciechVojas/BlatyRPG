import assert from "node:assert/strict";
import test from "node:test";
import { ProtocolError, parseAuthenticatedMessage } from "../src/protocol.js";

test("accepts scheduled jukebox commands without client campaign scope", () => {
  const command = parseAuthenticatedMessage({
    v: 1,
    type: "JUKEBOX_PLAY",
    requestId: "jukebox-play-1",
    channelId: "ambient-1",
    trackId: 44,
    position: 31.842,
    duration: 180,
    sourceType: "upload",
    executeAt: 1760000000123,
  });
  assert.deepEqual(command, {
    type: "JUKEBOX_PLAY",
    requestId: "jukebox-play-1",
    channelId: "ambient-1",
    trackId: 44,
    position: 31.842,
    duration: 180,
    sourceType: "upload",
    executeAt: 1760000000123,
  });
});

test("rejects client-owned campaign and identity fields", () => {
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "JUKEBOX_STOP",
        requestId: "jukebox-stop-1",
        channelId: "music",
        campaignId: 999,
        userId: 1,
      }),
    (error) => error instanceof ProtocolError && error.code === "unexpected_field",
  );
});

test("validates clock synchronization messages", () => {
  assert.deepEqual(
    parseAuthenticatedMessage({
      v: 1,
      type: "JUKEBOX_SYNC",
      requestId: "clock-1",
      clientSentAt: 1760000000000,
    }),
    {
      type: "JUKEBOX_SYNC",
      requestId: "clock-1",
      clientSentAt: 1760000000000,
    },
  );
});

test("accepts an audio input as a channel source without client identity fields", () => {
  const parsed = parseAuthenticatedMessage({
    v: 1,
    type: "JUKEBOX_DEVICE",
    requestId: "device-1",
    channelId: "ambient-2",
    executeAt: 1760000000123,
    deviceLabel: "Voicemeeter AUX Output",
    deviceSlot: "external-1",
    volume: 0.7,
    muted: false,
  });

  assert.deepEqual(parsed, {
    type: "JUKEBOX_DEVICE",
    requestId: "device-1",
    channelId: "ambient-2",
    executeAt: 1760000000123,
    deviceLabel: "Voicemeeter AUX Output",
    deviceSlot: "external-1",
    volume: 0.7,
    muted: false,
  });
});
