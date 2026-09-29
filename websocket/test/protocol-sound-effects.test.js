import assert from "node:assert/strict";
import test from "node:test";
import { ProtocolError, parseAuthenticatedMessage } from "../src/protocol.js";

test("accepts every sound effect command with server-owned campaign identity", () => {
  assert.deepEqual(
    parseAuthenticatedMessage({
      v: 1,
      type: "SOUND_EFFECT_PLAY",
      requestId: "request-123",
      playbackId: "playback-123",
      slotId: 19,
      executeAt: 1760000000123,
    }),
    {
      type: "SOUND_EFFECT_PLAY",
      requestId: "request-123",
      playbackId: "playback-123",
      slotId: 19,
      executeAt: 1760000000123,
    },
  );
  assert.deepEqual(
    parseAuthenticatedMessage({
      v: 1,
      type: "SOUND_EFFECT_STOP",
      requestId: "request-124",
      playbackId: "playback-123",
    }),
    {
      type: "SOUND_EFFECT_STOP",
      requestId: "request-124",
      playbackId: "playback-123",
    },
  );
  assert.deepEqual(
    parseAuthenticatedMessage({
      v: 1,
      type: "SOUND_EFFECT_STOP_ALL",
      requestId: "request-125",
      fadeOutMs: 250,
    }),
    {
      type: "SOUND_EFFECT_STOP_ALL",
      requestId: "request-125",
      fadeOutMs: 250,
    },
  );
});

test("rejects client-owned sound effect campaign and audio payload fields", () => {
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "SOUND_EFFECT_PLAY",
        requestId: "request-123",
        playbackId: "playback-123",
        slotId: 19,
        campaignId: 999,
        audio: { url: "https://attacker.invalid/audio" },
      }),
    (error) =>
      error instanceof ProtocolError && error.code === "unexpected_field",
  );
});

test("validates sound effect playback identifiers and fades", () => {
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "SOUND_EFFECT_STOP",
        requestId: "request-126",
        playbackId: "bad id",
      }),
    (error) =>
      error instanceof ProtocolError &&
      error.code === "sound_effect_playback_id_invalid",
  );
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "SOUND_EFFECT_STOP_ALL",
        requestId: "request-127",
        fadeOutMs: 60001,
      }),
    (error) =>
      error instanceof ProtocolError &&
      error.code === "sound_effect_fade_invalid",
  );
});
