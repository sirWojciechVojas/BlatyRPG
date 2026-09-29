import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { AudioMixerService } from "../audioMixerService";

const audioParam = () => ({
  value: 1,
  cancelScheduledValues: vi.fn(),
  setValueAtTime: vi.fn(function setValueAtTime(value) {
    this.value = value;
  }),
  setTargetAtTime: vi.fn(function setTargetAtTime(value) {
    this.value = value;
  }),
  exponentialRampToValueAtTime: vi.fn(function ramp(value) {
    this.value = value;
  }),
});

const node = () => ({ connect: vi.fn(), disconnect: vi.fn() });

class FakeAudioContext {
  constructor() {
    this.state = "running";
    this.currentTime = 10;
    this.destination = node();
  }

  addEventListener() {}

  createGain() {
    return { ...node(), gain: audioParam() };
  }

  createBufferSource() {
    return {
      ...node(),
      buffer: null,
      loop: false,
      onended: null,
      start: vi.fn(),
      stop: vi.fn(function stop() {
        this.onended?.();
      }),
    };
  }

  createDynamicsCompressor() {
    return node();
  }

  createAnalyser() {
    return {
      ...node(),
      fftSize: 32,
      smoothingTimeConstant: 0,
      getFloatTimeDomainData: (samples) => samples.fill(0.2),
    };
  }

  decodeAudioData() {
    return Promise.resolve({ duration: 4 });
  }

  close() {
    this.state = "closed";
    return Promise.resolve();
  }
}

describe("AudioMixerService sound effect playback", () => {
  let mixer;
  let fetcher;

  beforeEach(() => {
    localStorage.clear();
    fetcher = vi.fn().mockResolvedValue({
      ok: true,
      arrayBuffer: () => Promise.resolve(new ArrayBuffer(8)),
    });
    mixer = new AudioMixerService({
      AudioContext: FakeAudioContext,
      fetch: fetcher,
    });
  });

  afterEach(async () => {
    await mixer.destroy();
  });

  it("caches decoded audio while playing multiple independent instances", async () => {
    const track = { id: 9, title: "Thunder", url: "/api/audio/9" };
    await mixer.playEffect({ playbackId: "effect-one", track, volume: 0.8 });
    await mixer.playEffect({ playbackId: "effect-two", track, loop: true });

    expect(fetcher).toHaveBeenCalledTimes(1);
    expect(Object.keys(mixer.effectSnapshot().instances)).toEqual([
      "effect-one",
      "effect-two",
    ]);
  });

  it("stops one effect or every active effect without touching jukebox channels", async () => {
    const track = { id: 10, title: "Door", url: "/api/audio/10" };
    await mixer.playEffect({ playbackId: "effect-one", track });
    await mixer.playEffect({ playbackId: "effect-two", track });
    mixer.channels.music.status = "playing";

    expect(mixer.stopEffect("effect-one")).toBe(true);
    expect(Object.keys(mixer.effectSnapshot().instances)).toEqual([
      "effect-two",
    ]);
    mixer.stopAllEffects();

    expect(mixer.effectSnapshot().instances).toEqual({});
    expect(mixer.channels.music.status).toBe("playing");
  });
});
