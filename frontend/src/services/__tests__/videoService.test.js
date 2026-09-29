import { beforeEach, describe, expect, it, vi } from "vitest";
import { Track } from "livekit-client";
import { VideoService, normalizeVideoError } from "../videoService";

const mediaTrack = () => ({
  kind: "video",
  readyState: "live",
  addEventListener: vi.fn(),
  stop: vi.fn(),
});

const stream = (track = mediaTrack()) => ({
  track,
  getVideoTracks: () => [track],
  getTracks: () => [track],
});

const localTrack = (track) => ({
  kind: Track.Kind.Video,
  mediaStreamTrack: track,
  attach: vi.fn(),
  detach: vi.fn(),
});

const setup = () => {
  const localParticipant = {
    identity: "user_1",
    name: "Local",
    metadata: '{"nickname":"Local user","role":"player"}',
    videoTrackPublications: new Map(),
  };
  const callbacks = {};
  const livekit = {
    room: { localParticipant, remoteParticipants: new Map() },
    subscribe: vi.fn((value) => {
      Object.assign(callbacks, value);
      return vi.fn();
    }),
    participants: vi.fn(() => [localParticipant]),
    publishVideo: vi.fn(async (track, quality) => {
      const publication = {
        kind: Track.Kind.Video,
        source: Track.Source.Camera,
        trackName: "video:camera",
        trackSid: `camera-${quality}`,
        isMuted: false,
        track: localTrack(track),
      };
      localParticipant.videoTrackPublications.set(
        publication.trackSid,
        publication,
      );
      return publication;
    }),
    replaceTrack: vi.fn(async (publication, track) => {
      publication.track.mediaStreamTrack = track;
      return publication;
    }),
    unpublish: vi.fn(async (publication) => {
      localParticipant.videoTrackPublications.delete(publication.trackSid);
    }),
  };
  let deviceListener = null;
  const devices = {
    subscribe: vi.fn((listener) => {
      deviceListener = listener;
      listener({ videoInputs: [{ deviceId: "cam-1", label: "Camera" }] });
      return vi.fn();
    }),
    emitDevices: vi.fn(async () => {
      const result = {
        videoInputs: [{ deviceId: "cam-1", label: "Camera" }],
      };
      deviceListener?.(result);
      return result;
    }),
    captureCamera: vi.fn(async () => stream()),
  };
  const service = new VideoService({
    livekit,
    devices,
    preferences: { cameraDeviceId: "cam-1", quality: "auto" },
  });
  return { service, livekit, devices, localParticipant, callbacks };
};

describe("VideoService camera lifecycle", () => {
  beforeEach(() => localStorage.clear());

  it("publishes camera independently and republishes it when quality changes", async () => {
    const { service, livekit, devices } = setup();
    await service.join(7);

    await service.setCameraEnabled(true);

    expect(devices.captureCamera).toHaveBeenCalledWith("cam-1", "auto");
    expect(livekit.publishVideo).toHaveBeenCalledWith(
      expect.objectContaining({ kind: "video" }),
      "auto",
    );
    expect(service.snapshot()).toMatchObject({
      campaignId: 7,
      cameraEnabled: true,
      quality: "auto",
    });

    await service.changeQuality("1080p");

    expect(livekit.unpublish).toHaveBeenCalledTimes(1);
    expect(devices.captureCamera).toHaveBeenLastCalledWith("cam-1", "1080p");
    expect(livekit.publishVideo).toHaveBeenLastCalledWith(
      expect.objectContaining({ kind: "video" }),
      "1080p",
    );
  });

  it("attaches every remote camera to the supplied video element", async () => {
    const { service, livekit } = setup();
    const remoteTrack = localTrack(mediaTrack());
    const publication = {
      kind: Track.Kind.Video,
      source: Track.Source.Camera,
      trackName: "video:camera",
      trackSid: "remote-camera",
      isMuted: false,
      track: remoteTrack,
    };
    const remote = {
      identity: "user_2",
      name: "Remote",
      metadata: '{"nickname":"Remote user","avatar":"avatar.png"}',
      videoTrackPublications: new Map([[publication.trackSid, publication]]),
    };
    livekit.room.remoteParticipants.set(remote.identity, remote);
    livekit.participants.mockReturnValue([
      livekit.room.localParticipant,
      remote,
    ]);
    await service.join(7);
    const element = document.createElement("video");

    service.attach(remote.identity, element);

    expect(remoteTrack.attach).toHaveBeenCalledWith(element);
    expect(service.snapshot().participants).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          identity: "user_2",
          nickname: "Remote user",
          cameraOn: true,
        }),
      ]),
    );

    service.detach(remote.identity, element);
    expect(remoteTrack.detach).toHaveBeenCalledWith(element);
  });

  it("cleans up publications, captures and attachments when leaving", async () => {
    const { service, livekit } = setup();
    await service.join(7);
    await service.setCameraEnabled(true);
    const publishedMediaTrack = service.localStream.getVideoTracks()[0];
    const element = document.createElement("video");
    service.attach("user_1", element);

    await service.leave();

    expect(livekit.unpublish).toHaveBeenCalledTimes(1);
    expect(publishedMediaTrack.stop).toHaveBeenCalled();
    expect(service.snapshot()).toMatchObject({
      campaignId: null,
      status: "disconnected",
      cameraEnabled: false,
      participants: [],
    });
    expect(service.attachments.size).toBe(0);
    expect(service.videoTracks.size).toBe(0);
  });

  it("republishes an ended camera track after reconnecting", async () => {
    const { service, livekit } = setup();
    await service.join(7);
    await service.setCameraEnabled(true);
    service.localPublication.track.mediaStreamTrack.readyState = "ended";

    await service.handleReconnected();

    expect(livekit.unpublish).toHaveBeenCalledTimes(1);
    expect(livekit.publishVideo).toHaveBeenCalledTimes(2);
    expect(service.snapshot()).toMatchObject({
      status: "connected",
      cameraEnabled: true,
      error: null,
    });
  });

  it("falls back to the default camera after devicechange removes the selection", async () => {
    const { service, livekit, devices } = setup();
    await service.join(7);
    await service.setCameraEnabled(true);

    service.handleDevices({ videoInputs: [] });
    await service.operation;

    expect(service.snapshot().cameraDeviceId).toBe("");
    expect(devices.captureCamera).toHaveBeenLastCalledWith("", "auto");
    expect(livekit.unpublish).toHaveBeenCalledTimes(1);
    expect(livekit.publishVideo).toHaveBeenCalledTimes(2);
  });

  it("normalizes camera permission failures for the UI", () => {
    const error = Object.assign(new Error("Permission denied"), {
      name: "NotAllowedError",
    });

    expect(normalizeVideoError(error)).toBe("camera_permission_denied");
  });
});
