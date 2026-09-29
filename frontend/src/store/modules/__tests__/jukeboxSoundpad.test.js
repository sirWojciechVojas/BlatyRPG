import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({
  command: vi.fn((type, channelId, payload) => ({
    type,
    channelId,
    ...payload,
  })),
  unlock: vi.fn().mockResolvedValue(undefined),
}));

vi.mock("@/services/jukeboxService", () => ({
  jukeboxService: {
    command: mocks.command,
    attach: vi.fn(),
  },
}));

vi.mock("@/services/audioMixerService", () => ({
  audioMixerService: { unlock: mocks.unlock },
}));

import jukebox from "../jukebox";

describe("Jukebox Soundpad playback overrides", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("uses the slot loop and volume when playing in the effects channel", async () => {
    const context = {
      state: {
        tracks: [
          {
            id: 91,
            attached: true,
            duration: 180,
            loop: false,
            sourceType: "external",
          },
        ],
        channels: { sfx: { volume: 0.2, muted: false } },
      },
      commit: vi.fn(),
      dispatch: vi.fn().mockResolvedValue(true),
    };

    await jukebox.actions.playTrackNow(context, {
      channelId: "sfx",
      trackId: 91,
      sourceLabel: "soundpad:18",
      loop: true,
      volume: 0.65,
    });

    expect(mocks.command).toHaveBeenCalledWith(
      "JUKEBOX_PLAY",
      "sfx",
      expect.objectContaining({
        trackId: 91,
        sourceLabel: "soundpad:18",
        loop: true,
        volume: 0.65,
      }),
    );
    expect(context.dispatch).toHaveBeenCalledWith(
      "realtime/sendJukebox",
      expect.objectContaining({
        type: "JUKEBOX_PLAY",
        channelId: "sfx",
      }),
      { root: true },
    );
  });
});
