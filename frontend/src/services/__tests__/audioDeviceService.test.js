import { describe, expect, it, vi } from "vitest";
import {
  AudioDeviceService,
  VOICE_CONSTRAINTS,
  audioInputAvailability,
} from "@/services/audioDeviceService";

describe("AudioDeviceService", () => {
  it("requests the selected microphone with voice processing enabled", async () => {
    const getUserMedia = vi.fn().mockResolvedValue({});
    const service = new AudioDeviceService({
      mediaDevices: { getUserMedia },
    });

    await service.captureMicrophone("microphone-7");

    expect(getUserMedia).toHaveBeenCalledWith({
      audio: {
        deviceId: { exact: "microphone-7" },
        ...VOICE_CONSTRAINTS,
      },
      video: false,
    });
  });

  it("does not notify a subscriber after it has been removed", async () => {
    let finishEnumeration;
    const enumerateDevices = vi.fn(
      () =>
        new Promise((resolve) => {
          finishEnumeration = resolve;
        }),
    );
    const mediaDevices = {
      enumerateDevices,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    };
    const service = new AudioDeviceService({ mediaDevices });
    const listener = vi.fn();

    const unsubscribe = service.subscribe(listener);
    unsubscribe();
    finishEnumeration([]);
    await Promise.resolve();

    expect(listener).not.toHaveBeenCalled();
    expect(mediaDevices.removeEventListener).toHaveBeenCalledWith(
      "devicechange",
      expect.any(Function),
    );
  });

  it("distinguishes an unconfigured, unavailable and available saved source", () => {
    const inputs = [{ deviceId: "voicemeeter-aux", label: "Voicemeeter AUX" }];

    expect(audioInputAvailability("", inputs).status).toBe("not-configured");
    expect(audioInputAvailability("disconnected-cable", inputs).status).toBe(
      "unavailable",
    );
    expect(audioInputAvailability("voicemeeter-aux", inputs)).toEqual({
      status: "available",
      device: inputs[0],
    });
  });
});
