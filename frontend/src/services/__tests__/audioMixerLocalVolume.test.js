import { beforeEach, describe, expect, it } from "vitest";
import { AudioMixerService } from "../audioMixerService";

describe("AudioMixerService local source volumes", () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it("stores an independent local volume for every jukebox source", () => {
    const mixer = new AudioMixerService({ AudioContext: null });

    mixer.setLocalVolume("ambient-1", 0.25);
    mixer.setLocalVolume("ambient-2", 0.8);

    expect(mixer.channels["ambient-1"].localVolume).toBe(0.25);
    expect(mixer.channels["ambient-2"].localVolume).toBe(0.8);
    expect(
      JSON.parse(window.localStorage.getItem("blatyrpg.audio.channel-volumes")),
    ).toMatchObject({ "ambient-1": 0.25, "ambient-2": 0.8 });
  });

  it("stores a local master mix from 0 to 140 percent with mute", () => {
    const mixer = new AudioMixerService({ AudioContext: null });
    mixer.channels.music.localVolume = 1;

    mixer.setMasterVolume(1.4);
    expect(mixer.masterVolume).toBe(1.4);
    expect(mixer.effectiveProviderVolume(mixer.channels.music)).toBe(1);

    mixer.setMasterMuted(true);
    expect(mixer.effectiveProviderVolume(mixer.channels.music)).toBe(0);
    expect(
      JSON.parse(window.localStorage.getItem("blatyrpg.audio.master-mix")),
    ).toEqual({ volume: 1.4, muted: true });
  });

  it("marks only the disconnected device channel as having lost its signal", () => {
    const mixer = new AudioMixerService({ AudioContext: null });
    const track = {};
    mixer.channels.music.title = "Voicemeeter AUX";
    mixer.channels.music.sourceLabel = "Voicemeeter AUX";
    mixer.channels.music.sourceType = "external-input";
    mixer.channels.music.status = "playing";
    mixer.channels["ambient-1"].status = "playing";
    mixer.records.set("music", {
      external: true,
      stream: { getTracks: () => [track] },
    });

    mixer.detachExternalInput("music", track);

    expect(mixer.channels.music).toMatchObject({
      title: "Voicemeeter AUX",
      status: "error",
      sourceUnavailable: true,
      meterAvailable: false,
    });
    expect(mixer.channels["ambient-1"].status).toBe("playing");
  });

  it("samples independent real-time levels for every active channel", () => {
    const mixer = new AudioMixerService({ AudioContext: null });
    const meter = (amplitude) => ({
      analyser: {
        getFloatTimeDomainData: (samples) => samples.fill(amplitude),
      },
      samples: new Float32Array(8),
      floorDb: -60,
      decay: 0.78,
      level: 0,
    });
    mixer.channels.music.status = "playing";
    mixer.channels["ambient-1"].status = "playing";
    mixer.records.set("music", meter(0.5));
    mixer.records.set("ambient-1", meter(0.05));

    mixer.sampleMeters();

    expect(mixer.channels.music.level).toBeGreaterThan(0.8);
    expect(mixer.channels["ambient-1"].level).toBeGreaterThan(0.5);
    expect(mixer.channels.music.level).toBeGreaterThan(
      mixer.channels["ambient-1"].level,
    );
  });
});
