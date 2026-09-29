import { describe, expect, it, vi } from "vitest";
import { MediaDeviceService, cameraConstraints } from "../mediaDeviceService";

describe("MediaDeviceService camera support", () => {
  it("enumerates video inputs alongside the existing audio devices", async () => {
    const service = new MediaDeviceService({
      mediaDevices: {
        enumerateDevices: vi.fn().mockResolvedValue([
          { kind: "audioinput", deviceId: "mic-1", label: "Microphone" },
          { kind: "videoinput", deviceId: "cam-1", label: "Front camera" },
          { kind: "videoinput", deviceId: "cam-2", label: "" },
          { kind: "audiooutput", deviceId: "out-1", label: "Headphones" },
          { kind: "other", deviceId: "ignored", label: "Ignored" },
        ]),
      },
    });

    const devices = await service.enumerate();

    expect(devices.videoInputs).toEqual([
      expect.objectContaining({ deviceId: "cam-1", label: "Front camera" }),
      expect.objectContaining({ deviceId: "cam-2", label: "Camera 3" }),
    ]);
    expect(devices.inputs).toHaveLength(1);
    expect(devices.outputs).toHaveLength(1);
  });

  it("captures only the selected camera with the requested quality profile", async () => {
    const getUserMedia = vi.fn().mockResolvedValue({});
    const service = new MediaDeviceService({ mediaDevices: { getUserMedia } });

    await service.captureCamera("cam-1080", "1080p");

    expect(getUserMedia).toHaveBeenCalledWith(
      cameraConstraints("cam-1080", "1080p"),
    );
    expect(getUserMedia).toHaveBeenCalledWith({
      audio: false,
      video: {
        deviceId: { exact: "cam-1080" },
        width: { ideal: 1920 },
        height: { ideal: 1080 },
        frameRate: { ideal: 30, max: 30 },
      },
    });
  });
});
