import { afterEach, describe, expect, it, vi } from "vitest";
import { AudioMixerService } from "../audioMixerService";
import { YouTubeAudioProvider } from "../audioProviders/youtubeProvider";

const gainParam = () => ({
  value: 1,
  cancelScheduledValues: vi.fn(),
  setTargetAtTime: vi.fn(),
  setValueAtTime: vi.fn(),
});

class AudioContextStub {
  constructor() {
    this.state = "running";
    this.currentTime = 0;
    this.destination = {};
  }

  addEventListener() {}

  createGain() {
    return { connect: vi.fn(), disconnect: vi.fn(), gain: gainParam() };
  }

  async resume() {
    this.state = "running";
  }

  async close() {
    this.state = "closed";
  }
}

const installYouTube = () => {
  let instance;
  let playerState = -1;
  window.YT = {
    PlayerState: { ENDED: 0, PLAYING: 1, PAUSED: 2, BUFFERING: 3 },
    Player: vi.fn(function Player(_slot, options) {
      const iframe = document.createElement("iframe");
      instance = {
        options,
        iframe,
        playVideo: vi.fn(),
        pauseVideo: vi.fn(),
        stopVideo: vi.fn(),
        seekTo: vi.fn(),
        mute: vi.fn(),
        unMute: vi.fn(),
        setVolume: vi.fn(),
        getCurrentTime: vi.fn(() => 0),
        getDuration: vi.fn(() => 180),
        getPlayerState: vi.fn(() => playerState),
        setPlayerState: (value) => {
          playerState = value;
          options.events.onStateChange({ data: value });
        },
        getIframe: vi.fn(() => iframe),
        destroy: vi.fn(),
      };
      queueMicrotask(() => options.events.onReady({ target: instance }));
      return instance;
    }),
  };
  return () => instance;
};

afterEach(() => {
  vi.useRealTimers();
  delete window.YT;
  document
    .querySelectorAll('[id^="jukebox-provider-"]')
    .forEach((element) => element.remove());
});

describe("YouTubeAudioProvider", () => {
  it("reports blocked playback and provider errors from the iframe API", async () => {
    const current = installYouTube();
    const onBlocked = vi.fn();
    const onPlaying = vi.fn();
    const onError = vi.fn();
    const provider = new YouTubeAudioProvider(
      { providerReference: "dQw4w9WgXcQ" },
      { onBlocked, onPlaying, onError },
    );

    await provider.load();
    const player = current();

    expect(player.options).toMatchObject({ width: "320", height: "200" });
    expect(player.iframe.getAttribute("allow")).toContain("autoplay");
    expect(player.iframe.getAttribute("referrerpolicy")).toBe(
      "strict-origin-when-cross-origin",
    );
    expect(player.iframe.getAttribute("tabindex")).toBe("-1");
    expect(player.iframe.getAttribute("aria-hidden")).toBe("true");
    const host = document.getElementById("jukebox-provider-host");
    expect(host?.getAttribute("aria-hidden")).toBe("true");
    expect(host?.style.left).toBe("-10000px");
    expect(host?.style.opacity).toBe("0");
    expect(provider.duration()).toBe(180);

    player.options.events.onAutoplayBlocked();
    player.options.events.onStateChange({
      data: window.YT.PlayerState.PLAYING,
    });
    player.options.events.onError({ data: 101 });

    expect(onBlocked).toHaveBeenCalledOnce();
    expect(onPlaying).toHaveBeenCalledOnce();
    expect(onError).toHaveBeenCalledWith(
      expect.objectContaining({ code: "youtube_embedding_disabled" }),
    );
    provider.destroy();
  });

  it("lets the mixer retry a blocked YouTube channel after a user gesture", async () => {
    const current = installYouTube();
    const mixer = new AudioMixerService({ AudioContext: AudioContextStub });

    await mixer.load("music", {
      id: 7,
      title: "Theme",
      category: "music",
      sourceType: "external",
      provider: "youtube",
      providerReference: "dQw4w9WgXcQ",
    });
    await mixer.play("music", 0);
    const player = current();

    player.options.events.onAutoplayBlocked();
    expect(mixer.channels.music).toMatchObject({
      status: "playing",
      blocked: true,
      playbackError: "youtube_autoplay_blocked",
    });
    expect(mixer.snapshot().audioBlocked).toBe(true);

    await mixer.unlock();
    expect(player.playVideo).toHaveBeenCalledTimes(2);
    player.options.events.onStateChange({
      data: window.YT.PlayerState.PLAYING,
    });
    expect(mixer.channels.music.blocked).toBe(false);
    expect(mixer.channels.music.playbackError).toBeNull();

    await mixer.destroy();
  });

  it("retries an unintended pause while playback is meant to continue", async () => {
    vi.useFakeTimers();
    const current = installYouTube();
    const provider = new YouTubeAudioProvider({
      providerReference: "dQw4w9WgXcQ",
    });

    await provider.load();
    await provider.play(12);
    const player = current();
    expect(player.playVideo).toHaveBeenCalledOnce();

    player.setPlayerState(window.YT.PlayerState.PAUSED);
    await vi.advanceTimersByTimeAsync(350);
    expect(player.playVideo).toHaveBeenCalledTimes(2);

    provider.pause();
    player.setPlayerState(window.YT.PlayerState.PAUSED);
    await vi.advanceTimersByTimeAsync(350);
    expect(player.playVideo).toHaveBeenCalledTimes(2);
    provider.destroy();
  });
});
