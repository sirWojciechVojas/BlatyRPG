import { Track } from "livekit-client";
import {
  mediaDeviceService,
  normalizeVideoQuality,
} from "./mediaDeviceService";
import { livekitService } from "./livekitService";

const preferencesKey = "blatyrpg.video.preferences";
const emptyDevices = Object.freeze({ videoInputs: [] });

const readPreferences = () => {
  if (typeof localStorage === "undefined") return {};
  try {
    return JSON.parse(localStorage.getItem(preferencesKey)) || {};
  } catch (_error) {
    return {};
  }
};

const participantMetadata = (participant) => {
  try {
    return JSON.parse(participant?.metadata || "{}") || {};
  } catch (_error) {
    return {};
  }
};

const isVideoPublication = (publication) =>
  publication?.kind === Track.Kind.Video ||
  publication?.track?.kind === Track.Kind.Video ||
  publication?.track?.mediaStreamTrack?.kind === "video";

const cameraPublication = (participant) =>
  [...(participant?.videoTrackPublications?.values?.() || [])].find(
    (publication) =>
      publication.source === Track.Source.Camera ||
      publication.trackName === "video:camera",
  ) || null;

export const normalizeVideoError = (error, fallback = "camera_failed") => {
  const name = String(error?.name || "");
  const message = String(error?.message || error || "");
  if (
    ["NotAllowedError", "PermissionDeniedError"].includes(name) ||
    /permission denied|permission dismissed|not allowed/iu.test(message)
  ) {
    return "camera_permission_denied";
  }
  if (
    [
      "NotFoundError",
      "DevicesNotFoundError",
      "OverconstrainedError",
      "ConstraintNotSatisfiedError",
    ].includes(name) ||
    /requested device not found|no device found/iu.test(message)
  ) {
    return "camera_not_found";
  }
  if (
    ["NotReadableError", "TrackStartError", "AbortError"].includes(name) ||
    /could not start video source|device.*(?:busy|unavailable)/iu.test(message)
  ) {
    return "camera_unavailable";
  }
  if (name === "SecurityError" || /secure context/iu.test(message)) {
    return "camera_insecure_context";
  }
  return String(error?.code || message || fallback);
};

export class VideoService {
  constructor(options = {}) {
    const preferences = options.preferences || readPreferences();
    this.livekit = options.livekit || livekitService;
    this.devices = options.devices || mediaDeviceService;
    this.campaignId = null;
    this.status = "disconnected";
    this.cameraEnabled = false;
    this.cameraDeviceId = String(preferences.cameraDeviceId || "");
    this.quality = normalizeVideoQuality(preferences.quality);
    this.busy = false;
    this.error = null;
    this.deviceList = emptyDevices;
    this.participants = [];
    this.activeSpeakers = new Set();
    this.localPublication = null;
    this.localStream = null;
    this.videoTracks = new Map();
    this.attachments = new Map();
    this.listeners = new Set();
    this.unsubscribeDevices = null;
    this.unsubscribeLiveKit = null;
    this.generation = 0;
    this.recoveryPending = false;
    this.operation = Promise.resolve();
  }

  initialize() {
    if (!this.unsubscribeLiveKit && this.livekit?.subscribe) {
      this.unsubscribeLiveKit = this.livekit.subscribe({
        status: (status) => {
          if (!this.campaignId) return;
          this.status = status;
          this.emit();
        },
        reconnected: () => void this.handleReconnected(),
        disconnected: () => {
          if (!this.campaignId) return;
          this.status = "disconnected";
          this.emit();
        },
        participants: () => this.refreshParticipants(),
        trackSubscribed: (track, publication, participant) => {
          if (!this.campaignId || !isVideoPublication(publication)) return;
          this.registerTrack(participant.identity, track, publication);
          this.refreshParticipants();
        },
        trackUnsubscribed: (track, publication, participant) => {
          if (!this.campaignId || !isVideoPublication(publication)) return;
          this.removeTrack(participant.identity, track);
          this.refreshParticipants();
        },
        trackPublished: (publication) => {
          if (this.campaignId && isVideoPublication(publication)) {
            this.refreshParticipants();
          }
        },
        trackUnpublished: (publication, participant) => {
          if (!this.campaignId || !isVideoPublication(publication)) return;
          this.removeTrack(participant.identity, publication.track);
          this.refreshParticipants();
        },
        localTrackPublished: (publication, participant) => {
          if (!this.campaignId || !isVideoPublication(publication)) return;
          this.registerTrack(
            participant.identity,
            publication.track,
            publication,
          );
          this.refreshParticipants();
        },
        localTrackUnpublished: (publication, participant) => {
          if (!this.campaignId || !isVideoPublication(publication)) return;
          this.removeTrack(participant.identity, publication.track);
          this.refreshParticipants();
        },
        activeSpeakers: (speakers) => {
          this.activeSpeakers = new Set(
            (speakers || []).map((participant) => participant.identity),
          );
          this.refreshParticipants();
        },
      });
    }
    if (!this.unsubscribeDevices) {
      this.unsubscribeDevices = this.devices.subscribe((devices) =>
        this.handleDevices(devices),
      );
    }
  }

  async join(campaignId) {
    const id = Number(campaignId);
    if (!Number.isInteger(id) || id < 1) {
      throw new TypeError("campaign_id_invalid");
    }
    this.initialize();
    this.campaignId = id;
    this.status = "connected";
    this.error = null;
    this.refreshParticipants();
    if (this.cameraEnabled) {
      try {
        await this.ensurePublished();
      } catch (error) {
        this.error = normalizeVideoError(error, "camera_republish_failed");
      }
    }
    await this.devices.emitDevices().catch(() => {});
    this.emit();
  }

  setCameraEnabled(enabled) {
    const generation = this.generation;
    return this.enqueue(async () => {
      if (generation !== this.generation) return;
      const next = Boolean(enabled);
      if (next === this.cameraEnabled && (!next || this.localPublication))
        return;
      this.cameraEnabled = next;
      this.busy = true;
      this.error = null;
      this.emit();
      try {
        if (next) await this.publishCamera({ replace: false });
        else await this.releaseLocalCamera();
      } catch (error) {
        if (next) this.cameraEnabled = false;
        this.error = normalizeVideoError(error);
        throw error;
      } finally {
        this.busy = false;
        this.refreshParticipants();
      }
    });
  }

  toggleCamera() {
    return this.setCameraEnabled(!this.cameraEnabled);
  }

  changeCamera(deviceId) {
    const generation = this.generation;
    return this.enqueue(async () => {
      if (generation !== this.generation) return;
      const previous = this.cameraDeviceId;
      this.cameraDeviceId = String(deviceId || "");
      this.persistPreferences();
      this.error = null;
      if (!this.cameraEnabled) {
        this.emit();
        return;
      }
      this.busy = true;
      this.emit();
      try {
        await this.publishCamera({ replace: true });
      } catch (error) {
        this.cameraDeviceId = previous;
        this.persistPreferences();
        this.error = normalizeVideoError(error, "camera_change_failed");
        throw error;
      } finally {
        this.busy = false;
        this.refreshParticipants();
      }
    });
  }

  changeQuality(quality) {
    const generation = this.generation;
    return this.enqueue(async () => {
      if (generation !== this.generation) return;
      const normalized = normalizeVideoQuality(quality);
      if (normalized === this.quality) return;
      const previous = this.quality;
      this.quality = normalized;
      this.persistPreferences();
      this.error = null;
      if (!this.cameraEnabled) {
        this.emit();
        return;
      }
      this.busy = true;
      this.emit();
      try {
        await this.publishCamera({ republish: true });
      } catch (error) {
        this.quality = previous;
        this.persistPreferences();
        this.error = normalizeVideoError(error, "camera_quality_failed");
        throw error;
      } finally {
        this.busy = false;
        this.refreshParticipants();
      }
    });
  }

  refreshDevices() {
    this.initialize();
    return this.devices.emitDevices();
  }

  async publishCamera(options = {}) {
    if (!this.campaignId || !this.livekit.room) {
      throw new Error("voice_room_not_connected");
    }
    const generation = this.generation;
    const stream = await this.devices.captureCamera(
      this.cameraDeviceId,
      this.quality,
    );
    const mediaTrack = stream.getVideoTracks()[0];
    if (!mediaTrack) {
      this.stopStream(stream);
      throw new Error("camera_track_missing");
    }
    if (generation !== this.generation || !this.cameraEnabled) {
      this.stopStream(stream);
      return;
    }
    this.bindTrackEnded(mediaTrack);
    const previousStream = this.localStream;
    const previousPublication = this.localPublication;
    let previousReleased = false;
    try {
      if (options.republish && previousPublication) {
        this.localPublication = null;
        this.localStream = null;
        await this.livekit.unpublish(previousPublication);
        this.stopStream(previousStream);
        previousReleased = true;
      }
      if (options.replace && this.localPublication?.track) {
        await this.livekit.replaceTrack(this.localPublication, mediaTrack);
      } else {
        this.localPublication = await this.livekit.publishVideo(
          mediaTrack,
          this.quality,
        );
      }
      this.localStream = stream;
      this.stopStream(previousStream, mediaTrack);
      const identity = this.livekit.room?.localParticipant?.identity;
      if (identity) {
        this.registerTrack(
          identity,
          this.localPublication?.track,
          this.localPublication,
        );
      }
      await this.devices.emitDevices().catch(() => {});
      this.error = null;
    } catch (error) {
      this.stopStream(stream);
      this.localStream = previousReleased ? null : previousStream;
      this.localPublication = previousReleased ? null : previousPublication;
      throw error;
    }
  }

  async ensurePublished() {
    if (!this.cameraEnabled || !this.campaignId || !this.livekit.room) return;
    const track = this.localPublication?.track?.mediaStreamTrack;
    if (this.localPublication?.track && track?.readyState !== "ended") return;
    await this.publishCamera({ republish: Boolean(this.localPublication) });
  }

  async handleReconnected() {
    if (!this.campaignId) return;
    this.status = "connected";
    try {
      await this.ensurePublished();
      this.error = null;
    } catch (error) {
      this.error = normalizeVideoError(error, "camera_republish_failed");
    }
    this.refreshParticipants();
  }

  handleDevices(devices = emptyDevices) {
    this.deviceList = {
      videoInputs: Array.isArray(devices.videoInputs)
        ? devices.videoInputs
        : [],
    };
    const selectedAvailable = this.deviceList.videoInputs.some(
      (device) => device.deviceId === this.cameraDeviceId,
    );
    if (this.cameraDeviceId && !selectedAvailable) {
      this.cameraDeviceId = "";
      this.persistPreferences();
      if (this.cameraEnabled && this.campaignId) {
        this.error = "camera_lost";
        this.recoverCamera(
          this.localStream?.getVideoTracks?.()[0] || null,
          "camera_lost",
        );
      }
    }
    this.emit();
  }

  bindTrackEnded(track) {
    track.addEventListener(
      "ended",
      () => {
        if (
          !this.cameraEnabled ||
          !this.campaignId ||
          this.localStream?.getVideoTracks?.()[0] !== track
        ) {
          return;
        }
        this.error = "camera_lost";
        this.emit();
        this.recoverCamera(track, "camera_lost");
      },
      { once: true },
    );
  }

  refreshParticipants() {
    if (!this.campaignId) {
      this.participants = [];
      this.emit();
      return;
    }
    const participants = this.livekit.participants?.() || [];
    const availableIdentities = new Set();
    this.participants = participants.map((participant) => {
      availableIdentities.add(participant.identity);
      const details = participantMetadata(participant);
      const publication = cameraPublication(participant);
      if (publication?.track) {
        this.registerTrack(
          participant.identity,
          publication.track,
          publication,
        );
      } else if (!publication) {
        this.removeTrack(participant.identity);
      }
      const local = participant === this.livekit.room?.localParticipant;
      return {
        identity: participant.identity,
        nickname: details.nickname || participant.name || participant.identity,
        avatar: details.avatar || "",
        role: details.role || "player",
        local,
        hasCamera: Boolean(publication),
        cameraOn: Boolean(publication && publication.isMuted !== true),
        speaking: this.activeSpeakers.has(participant.identity),
        trackSid: publication?.trackSid || null,
      };
    });
    [...this.videoTracks.keys()].forEach((identity) => {
      if (!availableIdentities.has(identity)) this.removeTrack(identity);
    });
    this.emit();
  }

  registerTrack(identity, track, publication) {
    if (!identity || !track) return;
    const previous = this.videoTracks.get(identity);
    if (previous?.track === track) {
      previous.publication = publication;
      return;
    }
    if (previous)
      this.detachTrack(previous.track, this.attachments.get(identity));
    this.videoTracks.set(identity, { track, publication });
    (this.attachments.get(identity) || []).forEach((element) =>
      this.attachTrack(identity, track, element),
    );
  }

  removeTrack(identity, expectedTrack = null) {
    const record = this.videoTracks.get(identity);
    if (!record || (expectedTrack && record.track !== expectedTrack)) return;
    this.detachTrack(record.track, this.attachments.get(identity));
    this.videoTracks.delete(identity);
  }

  attach(identity, element) {
    if (!identity || !element) return;
    const elements = this.attachments.get(identity) || new Set();
    elements.add(element);
    this.attachments.set(identity, elements);
    const track = this.videoTracks.get(identity)?.track;
    if (track) this.attachTrack(identity, track, element);
  }

  detach(identity, element) {
    const elements = this.attachments.get(identity);
    if (element) {
      const track = this.videoTracks.get(identity)?.track;
      this.detachTrack(track, [element]);
      elements?.delete(element);
    }
    if (!elements?.size) this.attachments.delete(identity);
  }

  attachTrack(identity, track, element) {
    element.autoplay = true;
    element.playsInline = true;
    element.muted = identity === this.livekit.room?.localParticipant?.identity;
    try {
      track.attach(element);
    } catch (error) {
      this.error = normalizeVideoError(error, "remote_video_failed");
      this.emit();
    }
  }

  detachTrack(track, elements = []) {
    (elements || []).forEach((element) => {
      try {
        track?.detach?.(element);
      } catch (_error) {
        // A track can already be detached after an SFU unsubscribe event.
      }
      if (element?.srcObject) element.srcObject = null;
    });
  }

  async releaseLocalCamera() {
    const publication = this.localPublication;
    const stream = this.localStream;
    this.localPublication = null;
    this.localStream = null;
    if (publication) await this.livekit.unpublish(publication).catch(() => {});
    this.stopStream(stream);
    const identity = this.livekit.room?.localParticipant?.identity;
    if (identity) this.removeTrack(identity);
  }

  async leave(options = {}) {
    ++this.generation;
    await this.releaseLocalCamera();
    this.videoTracks.forEach((record, identity) =>
      this.detachTrack(record.track, this.attachments.get(identity)),
    );
    this.videoTracks.clear();
    this.attachments.clear();
    this.unsubscribeDevices?.();
    this.unsubscribeDevices = null;
    this.unsubscribeLiveKit?.();
    this.unsubscribeLiveKit = null;
    this.campaignId = null;
    this.status = "disconnected";
    this.participants = [];
    this.activeSpeakers.clear();
    this.busy = false;
    this.recoveryPending = false;
    this.error = null;
    if (options.preserveCamera !== true) this.cameraEnabled = false;
    this.emit();
  }

  subscribe(listener) {
    this.listeners.add(listener);
    listener(this.snapshot());
    return () => this.listeners.delete(listener);
  }

  snapshot() {
    return {
      campaignId: this.campaignId,
      status: this.status,
      cameraEnabled: this.cameraEnabled,
      cameraDeviceId: this.cameraDeviceId,
      quality: this.quality,
      busy: this.busy,
      error: this.error,
      devices: this.deviceList,
      participants: this.participants,
    };
  }

  persistPreferences() {
    if (typeof localStorage === "undefined") return;
    try {
      localStorage.setItem(
        preferencesKey,
        JSON.stringify({
          cameraDeviceId: this.cameraDeviceId,
          quality: this.quality,
        }),
      );
    } catch (_error) {
      // Camera preferences are best-effort in private browsing modes.
    }
  }

  recoverCamera(expectedTrack, fallback) {
    if (this.recoveryPending) return;
    const generation = this.generation;
    this.recoveryPending = true;
    void this.enqueue(async () => {
      if (generation !== this.generation || !this.cameraEnabled) return;
      const currentTrack = this.localStream?.getVideoTracks?.()[0] || null;
      if (expectedTrack && currentTrack && currentTrack !== expectedTrack)
        return;
      try {
        await this.publishCamera({ republish: Boolean(this.localPublication) });
        this.error = null;
      } catch (error) {
        this.error = normalizeVideoError(error, fallback);
      }
    }).finally(() => {
      this.recoveryPending = false;
      this.refreshParticipants();
    });
  }

  enqueue(task) {
    const operation = this.operation.catch(() => {}).then(task);
    this.operation = operation.catch(() => {});
    return operation;
  }

  stopStream(stream, except = null) {
    stream?.getTracks?.().forEach((track) => {
      if (track !== except) track.stop();
    });
  }

  emit() {
    const snapshot = this.snapshot();
    this.listeners.forEach((listener) => listener(snapshot));
  }
}

export const videoService = new VideoService();
