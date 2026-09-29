import { describe, expect, it, vi } from "vitest";
import { LiveKitService, videoPublishOptions } from "../livekitService";

class RoomStub {
  static instances = [];

  constructor(options) {
    this.options = options;
    this.handlers = new Map();
    this.localParticipant = {};
    this.remoteParticipants = new Map();
    this.canPlaybackAudio = true;
    this.connect = vi.fn(() => Promise.resolve());
    this.disconnect = vi.fn(() => Promise.resolve());
    this.switchActiveDevice = vi.fn(() => Promise.resolve(true));
    RoomStub.instances.push(this);
  }

  on(event, handler) {
    this.handlers.set(event, handler);
  }

  off(event) {
    this.handlers.delete(event);
  }
}

describe("LiveKitService Web Audio integration", () => {
  it("gives LiveKit the voice AudioContext and switches its output device", async () => {
    RoomStub.instances = [];
    const context = { state: "running" };
    const service = new LiveKitService({ Room: RoomStub });

    await service.connect("wss://voice.test", "token", {
      audioContext: context,
    });
    const room = RoomStub.instances[0];

    expect(room.options.webAudioMix).toEqual({ audioContext: context });
    expect(room.options).toMatchObject({
      adaptiveStream: true,
      dynacast: true,
    });
    await expect(service.setOutputDevice("headphones-1")).resolves.toBe(true);
    expect(room.switchActiveDevice).toHaveBeenCalledWith(
      "audiooutput",
      "headphones-1",
      true,
    );
  });

  it("publishes a 1080p camera as explicit 360p, 720p and 1080p simulcast layers", () => {
    const options = videoPublishOptions("1080p");

    expect(options).toMatchObject({
      name: "video:camera",
      simulcast: true,
      videoEncoding: { maxBitrate: 3_500_000, maxFramerate: 30 },
    });
    expect(options.videoSimulcastLayers).toHaveLength(2);
    expect(
      options.videoSimulcastLayers.map((layer) => layer.resolution),
    ).toEqual([
      expect.objectContaining({ width: 640, height: 360, frameRate: 20 }),
      expect.objectContaining({ width: 1280, height: 720, frameRate: 30 }),
    ]);
    expect(options.videoSimulcastLayers.map((layer) => layer.encoding)).toEqual(
      [
        expect.objectContaining({ maxBitrate: 500_000, maxFramerate: 20 }),
        expect.objectContaining({ maxBitrate: 1_500_000, maxFramerate: 30 }),
      ],
    );
  });
});
