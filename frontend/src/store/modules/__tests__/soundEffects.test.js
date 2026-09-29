import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
  mixer: {
    unlock: vi.fn().mockResolvedValue(undefined),
    preloadEffects: vi.fn().mockResolvedValue([]),
    playEffect: vi.fn().mockResolvedValue(true),
    stopEffect: vi.fn(),
    stopAllEffects: vi.fn(),
    setEffectMasterVolume: vi.fn(),
    setLocalVolume: vi.fn(),
    subscribeEffects: vi.fn(() => () => {}),
  },
  api: {},
  sync: { serverNow: vi.fn(() => 1760000000000) },
}));

vi.mock("@/lib/audio/soundEffectsApiClient", () => ({
  soundEffectsApiClient: mocks.api,
}));
vi.mock("@/services/audioMixerService", () => ({
  audioMixerService: mocks.mixer,
}));
vi.mock("@/services/jukeboxService", () => ({
  jukeboxService: { sync: mocks.sync },
}));

import soundEffects from "../soundEffects";

const context = (activePlaybacks = {}, effectChannel = {}) => ({
  state: {
    campaignId: 5,
    capabilities: { canControl: true },
    activePlaybacks,
    instances: {},
  },
  rootState: {
    jukebox: { channels: { sfx: effectChannel } },
  },
  dispatch: vi.fn().mockResolvedValue(true),
  commit: vi.fn(),
});

describe("sound effects store controls", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("sends play for an idle slot and stop when the same slot is active", async () => {
    const slot = { id: 17 };
    const idle = context();
    await soundEffects.actions.toggleSlot(idle, slot);
    expect(idle.dispatch).toHaveBeenCalledWith(
      "realtime/sendSoundEffect",
      expect.objectContaining({
        type: "SOUND_EFFECT_PLAY",
        slotId: 17,
        playbackId: expect.any(String),
      }),
      { root: true },
    );

    const active = context({
      "playback-17": { playbackId: "playback-17", slotId: 17 },
    });
    await soundEffects.actions.toggleSlot(active, slot);
    expect(active.dispatch).toHaveBeenCalledWith(
      "realtime/sendSoundEffect",
      expect.objectContaining({
        type: "SOUND_EFFECT_STOP",
        playbackId: "playback-17",
      }),
      { root: true },
    );
  });

  it("routes an external Soundpad source through the Jukebox effects channel", async () => {
    const slot = {
      id: 18,
      audioTrackId: 91,
      volume: 0.65,
      loop: true,
      audio: { id: 91, sourceType: "external", provider: "youtube" },
    };
    const vm = context();

    await soundEffects.actions.toggleSlot(vm, slot);

    expect(vm.dispatch).toHaveBeenCalledWith(
      "jukebox/playTrackNow",
      {
        channelId: "sfx",
        trackId: 91,
        sourceLabel: "soundpad:18",
        loop: true,
        volume: 0.65,
      },
      { root: true },
    );
    expect(vm.dispatch).not.toHaveBeenCalledWith(
      "realtime/sendSoundEffect",
      expect.objectContaining({ type: "SOUND_EFFECT_PLAY" }),
      { root: true },
    );
  });

  it("stops the Jukebox effects channel when the same external slot is pressed again", async () => {
    const slot = {
      id: 18,
      audioTrackId: 91,
      audio: { id: 91, sourceType: "external" },
    };
    const vm = context(
      {},
      {
        status: "playing",
        trackId: 91,
        sourceLabel: "soundpad:18",
      },
    );

    await soundEffects.actions.toggleSlot(vm, slot);

    expect(vm.dispatch).toHaveBeenCalledWith("jukebox/stop", "sfx", {
      root: true,
    });
    expect(vm.dispatch).not.toHaveBeenCalledWith(
      "jukebox/playTrackNow",
      expect.anything(),
      { root: true },
    );
  });

  it("sends a campaign stop-all command", async () => {
    const vm = context();
    await soundEffects.actions.stopAll(vm, 350);
    expect(vm.dispatch).toHaveBeenCalledWith(
      "realtime/sendSoundEffect",
      expect.objectContaining({
        type: "SOUND_EFFECT_STOP_ALL",
        fadeOutMs: 350,
      }),
      { root: true },
    );
  });

  it("also stops a Jukebox effect started by the Soundpad", async () => {
    const vm = context(
      {},
      {
        status: "playing",
        sourceLabel: "soundpad:18",
      },
    );

    await soundEffects.actions.stopAll(vm);

    expect(vm.dispatch).toHaveBeenCalledWith("jukebox/stop", "sfx", {
      root: true,
    });
  });

  it("applies the Soundpad master volume to buffered and Jukebox effects", () => {
    const vm = context();

    soundEffects.actions.setMasterVolume(vm, 0.4);

    expect(mocks.mixer.setEffectMasterVolume).toHaveBeenCalledWith(0.4);
    expect(mocks.mixer.setLocalVolume).toHaveBeenCalledWith("sfx", 0.4);
  });

  it("ignores realtime playback events from another campaign", async () => {
    const vm = context();
    await soundEffects.actions.handleRealtimeEvent(vm, {
      type: "SOUND_EFFECT_PLAY",
      campaignId: 6,
      payload: {
        playbackId: "foreign-playback",
        campaignId: 6,
        audio: { id: 1, url: "/api/audio/1" },
      },
    });
    expect(mocks.mixer.playEffect).not.toHaveBeenCalled();
    expect(vm.commit).not.toHaveBeenCalled();
  });
});
