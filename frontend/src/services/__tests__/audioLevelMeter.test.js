import { describe, expect, it } from "vitest";
import {
  createAudioLevelMeter,
  rmsToLevel,
  sampleAudioLevel,
} from "../audioLevelMeter";

describe("audioLevelMeter", () => {
  it("maps silence and useful voice amplitudes to a normalized meter", () => {
    expect(rmsToLevel(0)).toBe(0);
    expect(rmsToLevel(0.001)).toBeCloseTo(0, 5);
    expect(rmsToLevel(0.1)).toBeCloseTo(2 / 3, 2);
    expect(rmsToLevel(1)).toBe(1);
  });

  it("uses a fast attack and a gradual decay", () => {
    let amplitude = 0.1;
    const analyser = {
      fftSize: 4,
      getFloatTimeDomainData(samples) {
        samples.fill(amplitude);
      },
    };
    const meter = createAudioLevelMeter({ createAnalyser: () => analyser });

    const active = sampleAudioLevel(meter);
    amplitude = 0;
    const decaying = sampleAudioLevel(meter);

    expect(active).toBeGreaterThan(0.6);
    expect(decaying).toBeGreaterThan(0);
    expect(decaying).toBeLessThan(active);
  });
});
