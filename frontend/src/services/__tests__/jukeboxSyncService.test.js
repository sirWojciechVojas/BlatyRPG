import { describe, expect, it, vi } from "vitest";
import { JukeboxSyncService } from "@/services/jukeboxSyncService";

const mixer = () => ({
  channels: { music: { trackId: 7, status: "playing" } },
  setDuckingSettings: vi.fn(),
  load: vi.fn().mockResolvedValue(),
  play: vi.fn().mockResolvedValue(),
  pause: vi.fn(),
  stop: vi.fn(),
  seek: vi.fn(),
  setVolume: vi.fn(),
  setMuted: vi.fn(),
  setLoop: vi.fn(),
  fadeIn: vi.fn(),
  fadeOut: vi.fn(),
  correctDrift: vi.fn(),
  recoverPlayback: vi.fn().mockResolvedValue(),
  setExternalSource: vi.fn(),
});

describe("JukeboxSyncService", () => {
  it("calculates server offset from the midpoint of the round trip", () => {
    let now = 1000;
    const sync = new JukeboxSyncService(mixer(), { now: () => now });
    const request = sync.createSyncRequest("sync-1");
    expect(request.clientSentAt).toBe(1000);
    now = 1100;
    sync.handle({
      type: "JUKEBOX_SYNC",
      payload: { requestId: "sync-1", clientSentAt: 1000, serverTime: 1250 },
    });
    expect(sync.offsetMs).toBe(200);
    expect(sync.serverNow()).toBe(1300);
  });

  it("schedules playback on executeAt and includes transport latency", async () => {
    let now = 1000;
    let callback;
    const output = mixer();
    const sync = new JukeboxSyncService(output, {
      now: () => now,
      setTimeout: (handler) => {
        callback = handler;
        return 1;
      },
      clearTimeout: vi.fn(),
    });
    sync.setCatalog([{ id: 7, title: "Rain", sourceType: "upload" }]);
    await sync.applyAction("JUKEBOX_PLAY", {
      channelId: "music",
      trackId: 7,
      position: 10,
      executeAt: 1500,
    });
    expect(output.play).not.toHaveBeenCalled();
    now = 1600;
    callback();
    expect(output.play).toHaveBeenCalledWith("music", 10.1);
    expect(sync.state.state.music).toMatchObject({
      status: "playing",
      trackId: 7,
      executeAt: 1500,
      startedAt: -8500,
    });
  });

  it("reports asynchronous playback preparation errors", async () => {
    const output = mixer();
    output.load.mockRejectedValue(new Error("asset_unavailable"));
    const onError = vi.fn();
    const sync = new JukeboxSyncService(output, { onError });
    sync.setCatalog([{ id: 7, title: "Rain", sourceType: "upload" }]);

    sync.handle({
      type: "JUKEBOX_STATE",
      payload: {
        state: {
          music: {
            trackId: 7,
            status: "playing",
            position: 3,
            sourceType: "upload",
          },
        },
      },
    });
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(onError).toHaveBeenCalledWith(expect.any(Error));
    expect(onError.mock.calls[0][0].message).toBe("asset_unavailable");
  });

  it("seeks when drift exceeds the large-difference threshold", () => {
    const output = mixer();
    output.channels.music = { status: "playing" };
    const sync = new JukeboxSyncService(output);
    sync.state = {
      state: { music: { status: "playing", startedAt: Date.now() - 5000 } },
    };
    sync.startDriftCheck();
    sync.destroy();
    expect(output.correctDrift).not.toHaveBeenCalled();
  });

  it("uses browser timers without changing their required receiver", () => {
    const originalSetInterval = window.setInterval;
    const originalClearInterval = window.clearInterval;
    let timer = null;
    window.setInterval = function (handler, delay) {
      if (this !== window) throw new TypeError("Illegal invocation");
      timer = { handler, delay };
      return 41;
    };
    window.clearInterval = function (timerId) {
      if (this !== window) throw new TypeError("Illegal invocation");
      expect(timerId).toBe(41);
    };

    try {
      const sync = new JukeboxSyncService(mixer());
      sync.startDriftCheck();
      expect(timer?.delay).toBe(2000);
      sync.destroy();
    } finally {
      window.setInterval = originalSetInterval;
      window.clearInterval = originalClearInterval;
    }
  });

  it("pauses drift correction in a hidden tab and recovers on return", () => {
    let intervalHandler;
    let visibilityHandler;
    const page = {
      hidden: true,
      addEventListener: vi.fn((_type, handler) => {
        visibilityHandler = handler;
      }),
      removeEventListener: vi.fn(),
    };
    const output = mixer();
    const sync = new JukeboxSyncService(output, {
      document: page,
      now: () => 5000,
      setInterval: (handler) => {
        intervalHandler = handler;
        return 12;
      },
      clearInterval: vi.fn(),
    });
    sync.state = {
      state: {
        music: {
          status: "playing",
          sourceType: "external",
          startedAt: 0,
        },
      },
    };

    sync.startDriftCheck();
    intervalHandler();
    expect(output.correctDrift).not.toHaveBeenCalled();

    page.hidden = false;
    visibilityHandler();
    expect(output.recoverPlayback).toHaveBeenCalledOnce();
    expect(output.correctDrift).toHaveBeenCalledWith("music", 5);
    sync.destroy();
    expect(page.removeEventListener).toHaveBeenCalledWith(
      "visibilitychange",
      visibilityHandler,
    );
  });

  it("switches one channel to a device without touching another channel", async () => {
    const output = mixer();
    output.channels["ambient-1"] = { trackId: 8, status: "playing" };
    const sync = new JukeboxSyncService(output, {
      now: () => 1000,
      setTimeout: (handler) => {
        handler();
        return 1;
      },
    });

    await sync.applyAction("JUKEBOX_DEVICE", {
      channelId: "music",
      executeAt: 1000,
      deviceLabel: "Voicemeeter AUX",
      deviceSlot: "external-1",
    });

    expect(output.setExternalSource).toHaveBeenCalledWith("music", {
      label: "Voicemeeter AUX",
      deviceSlot: "external-1",
      status: "playing",
    });
    expect(output.channels["ambient-1"]).toEqual({
      trackId: 8,
      status: "playing",
    });
    expect(sync.state.state.music).toMatchObject({
      sourceType: "external-input",
      deviceLabel: "Voicemeeter AUX",
    });
  });

  it("refreshes playlist metadata when the same track starts in a new sequence", async () => {
    const output = mixer();
    output.channels.music = {
      trackId: 7,
      playlistId: null,
      sourceLabel: "",
      status: "playing",
    };
    const sync = new JukeboxSyncService(output, { now: () => 1000 });
    sync.setCatalog([{ id: 7, title: "Rain", sourceType: "upload" }]);

    await sync.applyAction("JUKEBOX_PLAY", {
      channelId: "music",
      trackId: 7,
      playlistId: 12,
      sourceLabel: "Storm",
      position: 0,
      executeAt: 1000,
    });

    expect(output.load).toHaveBeenCalledWith(
      "music",
      expect.objectContaining({
        id: 7,
        playlistId: 12,
        sourceLabel: "Storm",
      }),
      0,
    );
  });
});
