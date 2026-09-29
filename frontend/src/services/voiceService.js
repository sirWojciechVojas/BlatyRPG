import {
  audioApiClient,
  normalizeDeviceSettings,
} from "@/lib/audio/audioApiClient";
import { authSession } from "@/lib/auth/authSession";
import { audioDeviceService } from "./audioDeviceService";
import { createAudioLevelMeter, sampleAudioLevel } from "./audioLevelMeter";
import { audioMixerService } from "./audioMixerService";
import { livekitService } from "./livekitService";
import { videoService } from "./videoService";

const clamp = (value, minimum = -1, maximum = 1) =>
  Math.max(minimum, Math.min(maximum, Number(value) || 0));
const preferencesKey = "blatyrpg.voice.participant-mix";
const noopDevices = { inputs: [], outputs: [] };
const emptyDeviceSettings = () => ({
  microphoneDeviceId: "",
  outputDeviceId: "",
  externalInputs: { "external-1": "", "external-2": "" },
});
const jukeboxChannelPattern = /^(music|ambient-[12]|sfx|external-[12])$/;

export const normalizeVoiceError = (error, fallback = "voice_failed") => {
  const name = String(error?.name || "");
  const message = String(error?.message || error || "");
  if (
    ["NotAllowedError", "PermissionDeniedError"].includes(name) ||
    /permission denied|permission dismissed|not allowed/iu.test(message)
  ) {
    return "microphone_permission_denied";
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
    return "microphone_not_found";
  }
  if (
    ["NotReadableError", "TrackStartError"].includes(name) ||
    /could not start audio source|device.*(?:busy|unavailable)/iu.test(message)
  ) {
    return "microphone_unavailable";
  }
  if (name === "SecurityError" || /secure context/iu.test(message)) {
    return "microphone_insecure_context";
  }
  return String(error?.code || message || fallback);
};

const readPreferences = () => {
  try {
    return JSON.parse(localStorage.getItem(preferencesKey)) || {};
  } catch (_error) {
    return {};
  }
};

const metadata = (participant) => {
  try {
    return JSON.parse(participant?.metadata || "{}") || {};
  } catch (_error) {
    return {};
  }
};

const microphonePublication = (participant) =>
  [...(participant?.audioTrackPublications?.values?.() || [])].find(
    (publication) =>
      !String(publication.trackName || "").startsWith("jukebox:"),
  );

export class VoiceService {
  constructor(options = {}) {
    this.api = options.api || audioApiClient;
    this.auth = options.auth || authSession;
    this.devices = options.devices || audioDeviceService;
    this.mixer = options.mixer || audioMixerService;
    this.livekit = options.livekit || livekitService;
    this.video =
      options.video === undefined
        ? options.livekit
          ? null
          : videoService
        : options.video;
    this.AudioContext =
      options.AudioContext ||
      (typeof window === "undefined"
        ? null
        : window.AudioContext || window.webkitAudioContext);
    this.context = null;
    this.campaignId = null;
    this.status = "disconnected";
    this.joining = false;
    this.muted = true;
    this.deafened = false;
    this.pushToTalk = false;
    this.pushToTalkPressed = false;
    this.microphoneDeviceId = "";
    this.outputDeviceId = "";
    this.externalDeviceIds = { "external-1": "", "external-2": "" };
    this.preferredDeviceSettings = emptyDeviceSettings();
    this.accountUserId = null;
    this.deviceSettingsStatus = "idle";
    this.deviceSettingsError = null;
    this.deviceSettingsLoadPromise = null;
    this.deviceSettingsSaveTimer = null;
    this.deviceSettingsSaveRevision = 0;
    this.localPublication = null;
    this.localStream = null;
    this.localMeter = null;
    this.external = new Map();
    this.remoteGraphs = new Map();
    this.remoteExternalTracks = new Map();
    this.remoteRecoveryPromise = null;
    this.participantPreferences =
      typeof localStorage === "undefined" ? {} : readPreferences();
    this.participants = [];
    this.activeSpeakers = new Set();
    this.deviceList = noopDevices;
    this.listeners = new Set();
    this.unsubscribeDevices = null;
    this.unsubscribeAuth = null;
    this.generation = 0;
    this.reconnectTimer = null;
    this.meterTimer = null;
    this.reconnectAttempt = 0;
    this.lastError = null;
    this.audioBlocked = false;
  }

  initialize() {
    if (!this.unsubscribeDevices) this.listenForDevices();
    if (!this.unsubscribeAuth) {
      if (typeof this.auth?.subscribe === "function") {
        this.unsubscribeAuth = this.auth.subscribe((session) => {
          void this.switchAccount(session).catch(() => {});
        });
      } else {
        void this.switchAccount(this.auth?.read?.()).catch(() => {});
      }
    }
  }

  async refreshDevices() {
    this.initialize();
    await this.devices.emitDevices();
  }

  async join(campaignId, options = {}) {
    const id = Number(campaignId);
    if (!Number.isInteger(id) || id < 1)
      throw new TypeError("campaign_id_invalid");
    if (
      this.campaignId === id &&
      ["connected", "reconnecting"].includes(this.status)
    )
      return;
    const reconnectAttempt = this.reconnectAttempt;
    await this.leave({ preserveReconnect: options.reconnecting === true });
    if (options.reconnecting === true) this.reconnectAttempt = reconnectAttempt;
    const generation = ++this.generation;
    this.campaignId = id;
    this.status = "connecting";
    this.joining = true;
    this.lastError = null;
    this.listenForDevices();
    this.emit();
    let microphoneStream = null;
    try {
      await this.ensureAccountDeviceSettings();
      await this.devices.emitDevices();
      const credentials = await this.api.token(id);
      if (generation !== this.generation) return;
      microphoneStream = await this.captureJoinedMicrophone();
      if (generation !== this.generation) {
        this.stopStream(microphoneStream);
        return;
      }
      await this.ensureContext();
      await this.livekit.connect(credentials.serverUrl, credentials.token, {
        audioContext: this.context,
        status: (status) => {
          if (generation !== this.generation) return;
          this.status = status;
          this.emit();
        },
        reconnected: () => {
          if (generation === this.generation) void this.handleReconnected();
        },
        disconnected: (reason) => {
          if (generation !== this.generation) return;
          this.status = "disconnected";
          this.lastError = reason ? String(reason) : null;
          this.emit();
          this.scheduleReconnect();
        },
        participants: () => {
          if (generation === this.generation) this.refreshParticipants();
        },
        trackSubscribed: (track, publication, participant) => {
          if (generation === this.generation)
            void this.attachRemote(track, publication, participant).catch(
              (error) => this.reportError(error, "remote_audio_failed"),
            );
        },
        trackUnsubscribed: (_track, publication, participant) => {
          if (generation === this.generation)
            this.detachRemote(participant.identity, publication.trackSid);
        },
        activeSpeakers: (speakers) => {
          if (generation === this.generation) this.setActiveSpeakers(speakers);
        },
        audioPlayback: (allowed) => {
          if (generation !== this.generation) return;
          this.audioBlocked = !allowed;
          this.emit();
        },
        mediaError: (error) => {
          if (generation !== this.generation) return;
          this.lastError = normalizeVoiceError(error, "media_device_error");
          this.emit();
        },
      });
      if (generation !== this.generation) {
        this.stopStream(microphoneStream);
        return;
      }
      await this.video?.join(id).catch(() => {});
      await this.rebuildRemoteAudio();
      await this.applyOutputDevice();
      await this.publishMicrophone(microphoneStream);
      microphoneStream = null;
      this.status = "connected";
      this.reconnectAttempt = 0;
      this.joining = false;
      this.refreshParticipants();
    } catch (error) {
      this.stopStream(microphoneStream);
      if (generation !== this.generation) return;
      const errorCode = normalizeVoiceError(error, "voice_join_failed");
      await this.leave();
      this.status = "error";
      this.joining = false;
      this.lastError = errorCode;
      this.emit();
      throw error;
    }
  }

  async publishMicrophone(preparedStream = null) {
    const stream =
      preparedStream ||
      (await this.devices.captureMicrophone(this.microphoneDeviceId));
    const track = stream.getAudioTracks()[0];
    if (!track) throw new Error("microphone_track_missing");
    this.bindTrackEnded(track, "microphone");
    const previousStream = this.localStream;
    this.localStream = stream;
    try {
      if (this.localPublication?.track) {
        await this.livekit.replaceTrack(this.localPublication, track);
        this.stopStream(previousStream);
      } else {
        this.localPublication = await this.livekit.publishAudio(
          track,
          "voice:microphone",
        );
      }
      this.attachLocalMeter(stream);
    } catch (error) {
      this.localStream = previousStream;
      this.stopStream(stream);
      throw error;
    }
    await this.applyMuteState();
    await this.devices.emitDevices();
    this.refreshParticipants();
  }

  async captureJoinedMicrophone() {
    const preferred = this.preferredDeviceSettings.microphoneDeviceId;
    if (!preferred) {
      this.microphoneDeviceId = "";
      return this.devices.captureMicrophone("");
    }
    try {
      const stream = await this.devices.captureMicrophone(preferred);
      this.microphoneDeviceId = preferred;
      return stream;
    } catch (error) {
      if (normalizeVoiceError(error) !== "microphone_not_found") throw error;
      this.microphoneDeviceId = "";
      return this.devices.captureMicrophone("");
    }
  }

  async changeMicrophone(deviceId) {
    const previousDeviceId = this.microphoneDeviceId;
    const previousPreferred = this.preferredDeviceSettings.microphoneDeviceId;
    this.microphoneDeviceId = String(deviceId || "");
    this.preferredDeviceSettings.microphoneDeviceId = this.microphoneDeviceId;
    try {
      if (this.status === "connected") await this.publishMicrophone();
      this.lastError = null;
      this.scheduleDeviceSettingsSave();
      this.emit();
    } catch (error) {
      this.microphoneDeviceId = previousDeviceId;
      this.preferredDeviceSettings.microphoneDeviceId = previousPreferred;
      this.reportError(error, "microphone_change_failed");
      throw error;
    }
  }

  async setMuted(muted) {
    this.muted = Boolean(muted);
    try {
      await this.applyMuteState();
      this.lastError = null;
      this.refreshParticipants();
    } catch (error) {
      this.reportError(error, "microphone_mute_failed");
      throw error;
    }
  }

  async toggleMute() {
    return this.setMuted(!this.muted);
  }

  setPushToTalk(enabled) {
    this.pushToTalk = Boolean(enabled);
    this.pushToTalkPressed = false;
    if (this.pushToTalk) this.muted = false;
    void this.applyMuteState().catch((error) =>
      this.reportError(error, "microphone_mute_failed"),
    );
    this.emit();
  }

  pressPushToTalk(pressed) {
    if (!this.pushToTalk) return;
    this.pushToTalkPressed = Boolean(pressed);
    void this.applyMuteState().catch((error) =>
      this.reportError(error, "microphone_mute_failed"),
    );
    this.emit();
  }

  async applyMuteState() {
    const muted = this.muted || (this.pushToTalk && !this.pushToTalkPressed);
    const track = this.localPublication?.track;
    if (!track) return;
    if (muted) await track.mute();
    else await track.unmute();
  }

  async publishExternalInput(channelId, deviceId, source = {}) {
    if (!jukeboxChannelPattern.test(channelId))
      throw new TypeError("external_channel_invalid");
    if (!deviceId) throw new TypeError("audio_input_device_required");
    await this.unpublishExternalInput(channelId);
    let stream = null;
    let publication = null;
    try {
      stream = await this.devices.captureExternalInput(deviceId);
      const track = stream.getAudioTracks()[0];
      if (!track) throw new Error("external_audio_track_missing");
      this.bindTrackEnded(track, channelId);
      publication = await this.livekit.publishAudio(
        track,
        `jukebox:${channelId}`,
        {
          external: true,
        },
      );
      this.external.set(channelId, {
        deviceId,
        deviceSlot: source.deviceSlot || null,
        label: source.label || track.label || "",
        stream,
        track,
        publication,
      });
      await this.mixer.attachExternalInput(channelId, track, source);
      this.lastError = null;
      this.emit();
    } catch (error) {
      this.external.delete(channelId);
      if (publication)
        await this.livekit.unpublish(publication).catch(() => {});
      this.stopStream(stream);
      this.reportError(error, "external_audio_publish_failed");
      throw error;
    }
  }

  async unpublishExternalInput(channelId) {
    const record = this.external.get(channelId);
    if (!record) return;
    this.external.delete(channelId);
    this.mixer.detachExternalInput(channelId, record.track);
    await this.livekit.unpublish(record.publication).catch(() => {});
    this.stopStream(record.stream);
    this.emit();
  }

  async setOutputDevice(deviceId) {
    const previousDeviceId = this.outputDeviceId;
    const previousPreferred = this.preferredDeviceSettings.outputDeviceId;
    this.outputDeviceId = String(deviceId || "");
    this.preferredDeviceSettings.outputDeviceId = this.outputDeviceId;
    try {
      const supported = await this.applyOutputDevice();
      this.lastError = null;
      this.scheduleDeviceSettingsSave();
      this.emit();
      return supported;
    } catch (error) {
      this.outputDeviceId = previousDeviceId;
      this.preferredDeviceSettings.outputDeviceId = previousPreferred;
      void this.applyOutputDevice().catch(() => {});
      this.reportError(error, "audio_output_change_failed");
      throw error;
    }
  }

  async applyOutputDevice() {
    const voiceSupported = typeof this.context?.setSinkId === "function";
    const liveKitSupported = await this.livekit.setOutputDevice?.(
      this.outputDeviceId,
    );
    if (voiceSupported && liveKitSupported !== true)
      await this.context.setSinkId(this.outputDeviceId || "default");
    const mixerSupported = await this.mixer.setOutputDevice(
      this.outputDeviceId,
    );
    return liveKitSupported === true || voiceSupported || mixerSupported;
  }

  setExternalInputDevice(channelId, deviceId) {
    if (!/^external-[12]$/.test(channelId))
      throw new TypeError("external_channel_invalid");
    const value = String(deviceId || "");
    this.externalDeviceIds[channelId] = value;
    this.preferredDeviceSettings.externalInputs[channelId] = value;
    this.scheduleDeviceSettingsSave();
    this.emit();
  }

  setParticipantVolume(identity, volume) {
    this.setParticipantPreference(identity, "volume", clamp(volume, 0, 1));
  }

  setParticipantPan(identity, pan) {
    this.setParticipantPreference(identity, "pan", clamp(pan, -1, 1));
  }

  setParticipantMuted(identity, muted) {
    this.setParticipantPreference(identity, "muted", Boolean(muted));
  }

  setDeafened(deafened) {
    this.deafened = Boolean(deafened);
    this.remoteGraphs.forEach((record) => this.applyGraphMix(record));
    this.emit();
  }

  setMasterVolume(volume) {
    this.mixer.setCategoryVolume("voice", clamp(volume, 0, 1));
    this.remoteGraphs.forEach((record) => this.applyGraphMix(record));
    this.emit();
  }

  async unlockAudio() {
    await this.ensureContext();
    await this.rebuildRemoteAudio();
    const results = await Promise.allSettled([
      this.livekit.startAudio(),
      this.mixer.unlock(),
    ]);
    const failed = results.find((result) => result.status === "rejected");
    if (failed) {
      this.reportError(failed.reason, "audio_unlock_failed");
      throw failed.reason;
    }
    this.audioBlocked = false;
    this.emit();
  }

  async leave(options = {}) {
    ++this.generation;
    clearTimeout(this.reconnectTimer);
    this.reconnectTimer = null;
    if (options.preserveReconnect !== true) this.reconnectAttempt = 0;
    if (options.preserveReconnect !== true) this.deafened = false;
    this.joining = false;
    this.unsubscribeDevices?.();
    this.unsubscribeDevices = null;
    await this.video
      ?.leave({ preserveCamera: options.preserveReconnect === true })
      .catch(() => {});
    for (const channelId of [...this.external.keys()])
      await this.unpublishExternalInput(channelId);
    if (this.localPublication)
      await this.livekit.unpublish(this.localPublication).catch(() => {});
    this.localPublication = null;
    this.destroyLocalMeter();
    this.stopStream(this.localStream);
    this.localStream = null;
    this.remoteGraphs.forEach((record) => this.destroyGraph(record));
    this.remoteGraphs.clear();
    this.stopMetering();
    this.remoteExternalTracks.clear();
    await this.livekit.disconnect().catch(() => {});
    if (this.context && this.context.state !== "closed")
      await this.context.close().catch(() => {});
    this.context = null;
    this.campaignId = null;
    this.status = "disconnected";
    this.participants = [];
    this.activeSpeakers.clear();
    this.mixer.setVoiceActive(false);
    this.audioBlocked = false;
    this.lastError = null;
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
      joining: this.joining,
      muted: this.muted,
      deafened: this.deafened,
      pushToTalk: this.pushToTalk,
      pushToTalkPressed: this.pushToTalkPressed,
      microphoneDeviceId: this.microphoneDeviceId,
      outputDeviceId: this.outputDeviceId,
      externalDeviceIds: { ...this.externalDeviceIds },
      deviceSettingsStatus: this.deviceSettingsStatus,
      deviceSettingsError: this.deviceSettingsError,
      devices: this.deviceList,
      participants: this.participants,
      externalInputs: Object.fromEntries(
        [...this.external.entries()].map(([id, record]) => [
          id,
          record.deviceId,
        ]),
      ),
      outputSelectionSupported:
        typeof this.context?.setSinkId === "function" ||
        typeof this.AudioContext?.prototype?.setSinkId === "function",
      audioBlocked: this.audioBlocked,
      audioContextState: this.context?.state || "unavailable",
      error: this.lastError,
      voiceVolume: this.mixer.categoryVolumes.voice,
    };
  }

  async ensureContext() {
    if (!this.AudioContext) throw new Error("web_audio_unavailable");
    if (!this.context || this.context.state === "closed") {
      this.context = new this.AudioContext({ latencyHint: "interactive" });
      this.context.addEventListener?.("statechange", () => {
        if (this.context?.state === "closed" && this.campaignId) {
          this.lastError = "audio_context_closed";
          this.audioBlocked = true;
          this.emit();
          return;
        }
        if (this.context?.state === "suspended" && this.campaignId)
          this.audioBlocked = true;
        if (this.context?.state === "running") this.audioBlocked = false;
        this.emit();
      });
    }
    if (this.context.state === "suspended") await this.context.resume();
    return this.context;
  }

  async rebuildRemoteAudio() {
    for (const record of this.remoteGraphs.values()) this.destroyGraph(record);
    this.remoteGraphs.clear();
    const tasks = [];
    for (const participant of this.livekit.room?.remoteParticipants?.values?.() ||
      []) {
      for (const publication of participant.audioTrackPublications.values()) {
        if (publication.track) {
          tasks.push(
            this.attachRemote(publication.track, publication, participant),
          );
        }
      }
    }
    await Promise.allSettled(tasks);
  }

  ensureRemoteAudio() {
    if (this.remoteRecoveryPromise) return this.remoteRecoveryPromise;
    const generation = this.generation;
    const recover = async () => {
      const tasks = [];
      for (const participant of this.livekit.room?.remoteParticipants?.values?.() ||
        []) {
        for (const publication of participant.audioTrackPublications.values()) {
          const key = `${participant.identity}:${publication.trackSid}`;
          if (
            generation === this.generation &&
            publication.track &&
            !this.remoteGraphs.has(key) &&
            !this.remoteExternalTracks.has(key)
          ) {
            tasks.push(
              this.attachRemote(publication.track, publication, participant),
            );
          }
        }
      }
      await Promise.allSettled(tasks);
    };
    const promise = recover().finally(() => {
      if (this.remoteRecoveryPromise === promise)
        this.remoteRecoveryPromise = null;
    });
    this.remoteRecoveryPromise = promise;
    return promise;
  }

  async attachRemote(track, publication, participant) {
    const mediaTrack = track?.mediaStreamTrack;
    const kind = publication?.kind || track?.kind || mediaTrack?.kind;
    if (!mediaTrack || (kind && kind !== "audio")) return;
    const externalMatch = String(publication?.trackName || "").match(
      /^jukebox:(music|ambient-[12]|sfx|external-[12])$/,
    );
    if (externalMatch) {
      await this.mixer.attachExternalInput(externalMatch[1], mediaTrack);
      this.remoteExternalTracks.set(
        `${participant.identity}:${publication.trackSid}`,
        externalMatch[1],
      );
      return;
    }
    await this.ensureContext();
    const key = `${participant.identity}:${publication.trackSid}`;
    this.detachRemote(participant.identity, publication.trackSid);
    const gain = this.context.createGain();
    const panner = this.context.createStereoPanner();
    const meter = createAudioLevelMeter(this.context);
    const sdkPipeline =
      typeof track.attach === "function" &&
      typeof track.setWebAudioPlugins === "function";
    let source = null;
    let stream = null;
    let element = null;
    if (sdkPipeline) {
      track.setAudioContext?.(this.context);
      track.setWebAudioPlugins([
        ...(meter ? [meter.analyser] : []),
        gain,
        panner,
      ]);
      element = track.attach();
      element.autoplay = true;
      element.hidden = true;
      element.setAttribute("aria-hidden", "true");
      element.dataset.voiceParticipant = participant.identity;
      document.body?.appendChild(element);
    } else {
      stream = new MediaStream([mediaTrack]);
      source = this.context.createMediaStreamSource(stream);
      if (meter) {
        source.connect(meter.analyser);
        meter.analyser.connect(gain);
      } else {
        source.connect(gain);
      }
      gain.connect(panner);
      panner.connect(this.context.destination);
    }
    const record = {
      key,
      identity: participant.identity,
      stream,
      source,
      gain,
      panner,
      track,
      element,
      sdkPipeline,
      ...(meter || {}),
    };
    this.remoteGraphs.set(key, record);
    this.applyGraphMix(record);
    this.startMetering();
    this.refreshParticipants();
  }

  detachRemote(identity, trackSid) {
    const key = `${identity}:${trackSid}`;
    const record = this.remoteGraphs.get(key);
    if (record) {
      this.remoteGraphs.delete(key);
      this.destroyGraph(record);
    }
    const externalChannel = this.remoteExternalTracks.get(key);
    if (externalChannel) {
      this.remoteExternalTracks.delete(key);
      this.mixer.detachExternalInput(externalChannel);
    }
    this.stopMeteringIfIdle();
    this.refreshParticipants();
  }

  destroyGraph(record) {
    if (record.sdkPipeline && record.element) {
      try {
        record.track?.detach(record.element);
      } catch (_error) {
        // The SDK may already have detached an unsubscribed track.
      }
      record.element.remove();
      try {
        record.track?.setWebAudioPlugins([]);
      } catch (_error) {
        // The remote track may already be disposed by LiveKit.
      }
    }
    [record.source, record.analyser, record.gain, record.panner].forEach(
      (node) => {
        try {
          node?.disconnect();
        } catch (_error) {
          // A disconnected Web Audio node is already clean.
        }
      },
    );
  }

  attachLocalMeter(stream) {
    this.destroyLocalMeter();
    const meter = createAudioLevelMeter(this.context);
    if (!meter) return;
    let source = null;
    let sink = null;
    try {
      source = this.context.createMediaStreamSource(stream);
      sink = this.context.createGain();
      sink.gain.value = 0;
      source.connect(meter.analyser);
      meter.analyser.connect(sink);
      sink.connect(this.context.destination);
      this.localMeter = { ...meter, source, sink };
      this.startMetering();
    } catch (_error) {
      [source, meter.analyser, sink].forEach((node) => {
        try {
          node?.disconnect();
        } catch (_disconnectError) {
          // Metering is optional and never blocks voice publication.
        }
      });
    }
  }

  destroyLocalMeter() {
    const record = this.localMeter;
    this.localMeter = null;
    [record?.source, record?.analyser, record?.sink].forEach((node) => {
      try {
        node?.disconnect();
      } catch (_error) {
        // A disconnected Web Audio node is already clean.
      }
    });
    this.stopMeteringIfIdle();
  }

  participantLevel(identity, local = false) {
    if (local) return Number(this.localMeter?.level) || 0;
    let level = 0;
    this.remoteGraphs.forEach((record) => {
      if (record.identity === identity) level = Math.max(level, record.level);
    });
    return level;
  }

  participantMeterAvailable(identity, local = false) {
    if (local) return Boolean(this.localMeter?.analyser);
    return [...this.remoteGraphs.values()].some(
      (record) => record.identity === identity && record.analyser,
    );
  }

  startMetering() {
    if (this.meterTimer) return;
    this.meterTimer = setInterval(() => this.sampleMeters(), 50);
  }

  stopMeteringIfIdle() {
    const hasRemoteMeter = [...this.remoteGraphs.values()].some(
      (record) => record.analyser,
    );
    if (!this.localMeter && !hasRemoteMeter) this.stopMetering();
  }

  stopMetering() {
    clearInterval(this.meterTimer);
    this.meterTimer = null;
  }

  sampleMeters() {
    if (this.localMeter) sampleAudioLevel(this.localMeter);
    this.remoteGraphs.forEach((record) => {
      if (record.analyser) sampleAudioLevel(record);
    });
    let changed = false;
    this.participants = this.participants.map((participant) => {
      const level = this.participantLevel(
        participant.identity,
        participant.local,
      );
      if (Math.abs(level - (participant.level || 0)) < 0.005) {
        return participant;
      }
      changed = true;
      return { ...participant, level };
    });
    if (changed) this.emit();
  }

  setParticipantPreference(identity, key, value) {
    this.participantPreferences[identity] = {
      volume: 1,
      pan: 0,
      muted: false,
      ...(this.participantPreferences[identity] || {}),
      [key]: value,
    };
    try {
      localStorage.setItem(
        preferencesKey,
        JSON.stringify(this.participantPreferences),
      );
    } catch (_error) {
      // Private preferences are best-effort when storage is unavailable.
    }
    this.remoteGraphs.forEach((record) => {
      if (record.identity === identity) this.applyGraphMix(record);
    });
    this.refreshParticipants();
  }

  applyGraphMix(record) {
    const preference = this.participantPreferences[record.identity] || {};
    const master = this.mixer.categoryVolumes.voice;
    const now = this.context?.currentTime || 0;
    record.gain.gain.setTargetAtTime(
      clamp(preference.volume ?? 1, 0, 1) *
        master *
        (preference.muted === true || this.deafened ? 0 : 1),
      now,
      0.015,
    );
    record.panner.pan.setTargetAtTime(
      clamp(preference.pan ?? 0, -1, 1),
      now,
      0.015,
    );
  }

  setActiveSpeakers(speakers) {
    this.activeSpeakers = new Set(
      (speakers || []).map((item) => item.identity),
    );
    this.mixer.setVoiceActive(this.activeSpeakers.size > 0);
    this.refreshParticipants();
    const localIdentity = this.livekit.room?.localParticipant?.identity;
    const missingRemoteTrack = (speakers || []).some(
      (participant) =>
        participant.identity !== localIdentity &&
        ![...this.remoteGraphs.values()].some(
          (record) => record.identity === participant.identity,
        ),
    );
    if (missingRemoteTrack) {
      void this.ensureRemoteAudio().catch((error) =>
        this.reportError(error, "remote_audio_failed"),
      );
    }
  }

  refreshParticipants() {
    this.participants = this.livekit.participants().map((participant) => {
      const details = metadata(participant);
      const publication = microphonePublication(participant);
      const preference =
        this.participantPreferences[participant.identity] || {};
      const local = participant === this.livekit.room?.localParticipant;
      return {
        identity: participant.identity,
        userId: Number(details.userId) || null,
        nickname: details.nickname || participant.name || participant.identity,
        characterId: Number(details.characterId) || null,
        role: details.role || "player",
        avatar: details.avatar || "",
        local,
        hasMicrophone: Boolean(publication),
        muted: publication ? publication.isMuted === true : true,
        speaking: this.activeSpeakers.has(participant.identity),
        connectionQuality: String(participant.connectionQuality || "unknown"),
        volume: clamp(preference.volume ?? 1, 0, 1),
        pan: clamp(preference.pan ?? 0, -1, 1),
        localMuted: preference.muted === true,
        level: this.participantLevel(participant.identity, local),
        meterAvailable: this.participantMeterAvailable(
          participant.identity,
          local,
        ),
      };
    });
    this.emit();
  }

  async handleReconnected() {
    this.status = "connected";
    this.reconnectAttempt = 0;
    if (
      !this.localPublication?.track ||
      this.localPublication.track.mediaStreamTrack?.readyState === "ended"
    ) {
      await this.publishMicrophone().catch((error) => {
        this.lastError = normalizeVoiceError(
          error,
          "microphone_republish_failed",
        );
      });
    }
    this.refreshParticipants();
  }

  scheduleReconnect() {
    if (!this.campaignId || this.reconnectTimer) return;
    const campaignId = this.campaignId;
    const external = [...this.external.entries()].map(
      ([channelId, record]) => ({
        channelId,
        deviceId: record.deviceId,
      }),
    );
    const delay = Math.min(15000, 750 * 2 ** this.reconnectAttempt++);
    this.status = "reconnecting";
    this.reconnectTimer = setTimeout(async () => {
      this.reconnectTimer = null;
      try {
        await this.join(campaignId, { reconnecting: true });
        await Promise.allSettled(
          external.map((item) =>
            this.publishExternalInput(item.channelId, item.deviceId, item),
          ),
        );
      } catch (_error) {
        if (this.campaignId === campaignId) this.scheduleReconnect();
      }
    }, delay);
    this.emit();
  }

  bindTrackEnded(track, kind) {
    track.addEventListener(
      "ended",
      () => {
        if (kind === "microphone" && this.campaignId) {
          if (this.localStream?.getAudioTracks?.()[0] !== track) return;
          void this.publishMicrophone().catch((error) => {
            this.lastError = normalizeVoiceError(error, "microphone_lost");
            this.emit();
          });
        } else if (this.external.has(kind)) {
          const deviceId = this.external.get(kind).deviceId;
          void this.publishExternalInput(kind, deviceId).catch(() =>
            this.unpublishExternalInput(kind),
          );
        }
      },
      { once: true },
    );
  }

  listenForDevices() {
    this.unsubscribeDevices?.();
    this.unsubscribeDevices = this.devices.subscribe((devices) => {
      this.deviceList = devices;
      this.reconcileDeviceSelections(devices);
      this.emit();
    });
  }

  reconcileDeviceSelections(devices) {
    const availableInput = (deviceId) =>
      devices.inputs.some((item) => item.deviceId === deviceId);
    const availableOutput = (deviceId) =>
      devices.outputs.some((item) => item.deviceId === deviceId);
    const preferredMicrophone = this.preferredDeviceSettings.microphoneDeviceId;
    const nextMicrophone =
      preferredMicrophone && availableInput(preferredMicrophone)
        ? preferredMicrophone
        : "";
    if (nextMicrophone !== this.microphoneDeviceId) {
      this.microphoneDeviceId = nextMicrophone;
      if (this.status === "connected") {
        void this.publishMicrophone().catch((error) =>
          this.reportError(error, "microphone_republish_failed"),
        );
      }
    }

    const preferredOutput = this.preferredDeviceSettings.outputDeviceId;
    const nextOutput =
      preferredOutput && availableOutput(preferredOutput)
        ? preferredOutput
        : "";
    if (nextOutput !== this.outputDeviceId) {
      this.outputDeviceId = nextOutput;
      void this.applyOutputDevice().catch((error) =>
        this.reportError(error, "audio_output_change_failed"),
      );
    }

    ["external-1", "external-2"].forEach((channelId) => {
      const preferred = this.preferredDeviceSettings.externalInputs[channelId];
      this.externalDeviceIds[channelId] = preferred || "";
    });
  }

  async ensureAccountDeviceSettings() {
    const session = this.auth?.read?.() || null;
    const userId = Number(session?.user?.id) || null;
    if (
      userId !== this.accountUserId ||
      ["idle", "error"].includes(this.deviceSettingsStatus)
    )
      await this.switchAccount(session);
    if (this.deviceSettingsLoadPromise) await this.deviceSettingsLoadPromise;
  }

  async switchAccount(session) {
    const userId = Number(session?.user?.id) || null;
    if (userId === this.accountUserId && this.deviceSettingsLoadPromise)
      return this.deviceSettingsLoadPromise;
    if (userId === this.accountUserId && this.deviceSettingsStatus === "saved")
      return;

    const accountChanged =
      this.accountUserId !== null && this.accountUserId !== userId;
    if (accountChanged && this.campaignId) await this.leave();

    clearTimeout(this.deviceSettingsSaveTimer);
    this.deviceSettingsSaveTimer = null;
    ++this.deviceSettingsSaveRevision;
    this.accountUserId = userId;
    this.preferredDeviceSettings = emptyDeviceSettings();
    this.microphoneDeviceId = "";
    this.outputDeviceId = "";
    this.externalDeviceIds = { "external-1": "", "external-2": "" };
    this.deviceSettingsError = null;

    if (!userId || typeof this.api.deviceSettings !== "function") {
      this.deviceSettingsStatus = "idle";
      this.deviceSettingsLoadPromise = null;
      this.emit();
      return;
    }

    this.deviceSettingsStatus = "loading";
    this.emit();
    const requestedUserId = userId;
    const promise = Promise.resolve(this.api.deviceSettings())
      .then((settings) => {
        if (this.accountUserId !== requestedUserId) return;
        this.preferredDeviceSettings = normalizeDeviceSettings(settings);
        this.microphoneDeviceId =
          this.preferredDeviceSettings.microphoneDeviceId;
        this.outputDeviceId = this.preferredDeviceSettings.outputDeviceId;
        this.externalDeviceIds = {
          ...this.preferredDeviceSettings.externalInputs,
        };
        this.reconcileDeviceSelections(this.deviceList);
        this.deviceSettingsStatus = "saved";
        this.deviceSettingsError = null;
        this.emit();
      })
      .catch((error) => {
        if (this.accountUserId !== requestedUserId) return;
        this.deviceSettingsStatus = "error";
        this.deviceSettingsError = String(
          error?.code || error?.message || "audio_device_settings_load_failed",
        );
        this.emit();
      })
      .finally(() => {
        if (this.deviceSettingsLoadPromise === promise)
          this.deviceSettingsLoadPromise = null;
      });
    this.deviceSettingsLoadPromise = promise;
    return promise;
  }

  scheduleDeviceSettingsSave() {
    if (
      !this.accountUserId ||
      typeof this.api.saveDeviceSettings !== "function"
    )
      return;
    clearTimeout(this.deviceSettingsSaveTimer);
    const userId = this.accountUserId;
    const revision = ++this.deviceSettingsSaveRevision;
    const payload = normalizeDeviceSettings(this.preferredDeviceSettings);
    this.deviceSettingsStatus = "saving";
    this.deviceSettingsError = null;
    this.deviceSettingsSaveTimer = setTimeout(() => {
      this.deviceSettingsSaveTimer = null;
      void Promise.resolve(this.api.saveDeviceSettings(payload))
        .then(() => {
          if (
            this.accountUserId !== userId ||
            this.deviceSettingsSaveRevision !== revision
          )
            return;
          this.deviceSettingsStatus = "saved";
          this.deviceSettingsError = null;
          this.emit();
        })
        .catch((error) => {
          if (
            this.accountUserId !== userId ||
            this.deviceSettingsSaveRevision !== revision
          )
            return;
          this.deviceSettingsStatus = "error";
          this.deviceSettingsError = String(
            error?.code ||
              error?.message ||
              "audio_device_settings_save_failed",
          );
          this.emit();
        });
    }, 250);
    this.emit();
  }

  stopStream(stream, except = null) {
    stream?.getTracks?.().forEach((track) => {
      if (track !== except) track.stop();
    });
  }

  reportError(error, fallback) {
    this.lastError = normalizeVoiceError(error, fallback);
    this.emit();
  }

  emit() {
    const snapshot = this.snapshot();
    this.listeners.forEach((listener) => listener(snapshot));
  }
}

export const voiceService = new VoiceService();
