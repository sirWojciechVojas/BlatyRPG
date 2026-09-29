import { Room, RoomEvent, Track, VideoPreset } from "livekit-client";
import {
  VIDEO_QUALITY_PROFILES,
  normalizeVideoQuality,
} from "./mediaDeviceService";

const noop = () => {};

const videoPreset = (quality) => {
  const profile = VIDEO_QUALITY_PROFILES[quality];
  return new VideoPreset(
    profile.width,
    profile.height,
    profile.maxBitrate,
    profile.frameRate,
  );
};

export const videoPublishOptions = (quality = "auto") => {
  const normalized = normalizeVideoQuality(quality);
  const primaryQuality = normalized === "auto" ? "1080p" : normalized;
  const profile = VIDEO_QUALITY_PROFILES[primaryQuality];
  const layers = [];
  if (["720p", "1080p"].includes(primaryQuality)) {
    layers.push(videoPreset("360p"));
  }
  if (primaryQuality === "1080p") layers.push(videoPreset("720p"));
  return {
    name: "video:camera",
    source: Track.Source.Camera,
    simulcast: true,
    degradationPreference: "maintain-framerate",
    videoEncoding: {
      maxBitrate: profile.maxBitrate,
      maxFramerate: profile.frameRate,
    },
    ...(layers.length ? { videoSimulcastLayers: layers } : {}),
  };
};

export class LiveKitService {
  constructor(options = {}) {
    this.Room = options.Room || Room;
    this.room = null;
    this.callbacks = {};
    this.subscribers = new Set();
    this.bound = [];
  }

  async connect(serverUrl, token, callbacks = {}) {
    await this.disconnect();
    this.callbacks = callbacks;
    const roomOptions = {
      adaptiveStream: true,
      dynacast: true,
      disconnectOnPageLeave: true,
    };
    if (callbacks.audioContext) {
      roomOptions.webAudioMix = { audioContext: callbacks.audioContext };
    }
    this.room = new this.Room(roomOptions);
    this.bind(RoomEvent.Reconnecting, () =>
      this.call("status", "reconnecting"),
    );
    this.bind(RoomEvent.Reconnected, () => this.call("reconnected"));
    this.bind(RoomEvent.Disconnected, (reason) =>
      this.call("disconnected", reason),
    );
    this.bind(RoomEvent.ParticipantConnected, (participant) =>
      this.call("participants", participant),
    );
    this.bind(RoomEvent.ParticipantDisconnected, (participant) =>
      this.call("participants", participant),
    );
    this.bind(RoomEvent.ParticipantMetadataChanged, (_metadata, participant) =>
      this.call("participants", participant),
    );
    this.bind(RoomEvent.ParticipantNameChanged, (_name, participant) =>
      this.call("participants", participant),
    );
    this.bind(RoomEvent.ConnectionQualityChanged, (_quality, participant) =>
      this.call("participants", participant),
    );
    this.bind(RoomEvent.TrackSubscribed, (track, publication, participant) =>
      this.call("trackSubscribed", track, publication, participant),
    );
    this.bind(RoomEvent.TrackUnsubscribed, (track, publication, participant) =>
      this.call("trackUnsubscribed", track, publication, participant),
    );
    this.bind(RoomEvent.TrackPublished, (publication, participant) => {
      this.call("trackPublished", publication, participant);
      this.call("participants", participant);
    });
    this.bind(RoomEvent.TrackUnpublished, (publication, participant) => {
      this.call("trackUnpublished", publication, participant);
      this.call("participants", participant);
    });
    this.bind(RoomEvent.LocalTrackPublished, (publication, participant) => {
      this.call("localTrackPublished", publication, participant);
      this.call("participants", participant);
    });
    this.bind(RoomEvent.LocalTrackUnpublished, (publication, participant) => {
      this.call("localTrackUnpublished", publication, participant);
      this.call("participants", participant);
    });
    this.bind(RoomEvent.TrackMuted, (_publication, participant) =>
      this.call("participants", participant),
    );
    this.bind(RoomEvent.TrackUnmuted, (_publication, participant) =>
      this.call("participants", participant),
    );
    this.bind(RoomEvent.ActiveSpeakersChanged, (speakers) =>
      this.call("activeSpeakers", speakers),
    );
    this.bind(RoomEvent.AudioPlaybackStatusChanged, () =>
      this.call("audioPlayback", this.room?.canPlaybackAudio === true),
    );
    this.bind(RoomEvent.MediaDevicesError, (error) =>
      this.call("mediaError", error),
    );
    await this.room.connect(serverUrl, token, { autoSubscribe: true });
    this.call("status", "connected");
    this.call("participants");
    return this.room;
  }

  async publishAudio(mediaStreamTrack, name, options = {}) {
    if (!this.room) throw new Error("voice_room_not_connected");
    return this.room.localParticipant.publishTrack(mediaStreamTrack, {
      name,
      source: options.external ? Track.Source.Unknown : Track.Source.Microphone,
      dtx: options.external ? false : true,
      red: options.external ? false : true,
    });
  }

  async publishVideo(mediaStreamTrack, quality = "auto") {
    if (!this.room) throw new Error("voice_room_not_connected");
    return this.room.localParticipant.publishTrack(
      mediaStreamTrack,
      videoPublishOptions(quality),
    );
  }

  async replaceTrack(publication, mediaStreamTrack) {
    if (!publication?.track) throw new Error("local_media_track_missing");
    await publication.track.replaceTrack(mediaStreamTrack, true);
    return publication;
  }

  async unpublish(publication) {
    if (!this.room || !publication?.track) return;
    await this.room.localParticipant.unpublishTrack(publication.track, true);
  }

  async startAudio() {
    await this.room?.startAudio?.();
    return this.room?.canPlaybackAudio !== false;
  }

  async setOutputDevice(deviceId) {
    if (!this.room) return false;
    return this.room.switchActiveDevice(
      "audiooutput",
      deviceId || "default",
      true,
    );
  }

  participants() {
    if (!this.room) return [];
    return [
      this.room.localParticipant,
      ...this.room.remoteParticipants.values(),
    ];
  }

  async disconnect() {
    const room = this.room;
    this.unbindAll();
    this.room = null;
    this.callbacks = {};
    if (room) await room.disconnect();
  }

  subscribe(callbacks) {
    this.subscribers.add(callbacks);
    return () => this.subscribers.delete(callbacks);
  }

  bind(event, handler) {
    this.room.on(event, handler);
    this.bound.push([event, handler]);
  }

  unbindAll() {
    this.bound.forEach(([event, handler]) => this.room?.off(event, handler));
    this.bound = [];
  }

  call(name, ...args) {
    (this.callbacks[name] || noop)(...args);
    this.subscribers.forEach((callbacks) => (callbacks[name] || noop)(...args));
  }
}

export const livekitService = new LiveKitService();
