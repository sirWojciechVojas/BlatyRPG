import { resolveAccessToken } from "@/lib/api/jsonApiClient";
import { createAudioProvider } from "./audioProviders";
import { createAudioLevelMeter, sampleAudioLevel } from "./audioLevelMeter";

export const JUKEBOX_CHANNELS = Object.freeze([
  ["music", "music"],
  ["ambient-1", "ambient"],
  ["ambient-2", "ambient"],
  ["sfx", "sfx"],
  ["external-1", "external"],
  ["external-2", "external"],
]);

const clamp = (value, minimum = 0, maximum = 1) =>
  Math.max(minimum, Math.min(maximum, Number(value) || 0));
const dbGain = (db) => Math.pow(10, Number(db) / 20);
const storageKey = "blatyrpg.audio.category-volumes";
const channelVolumeStorageKey = "blatyrpg.audio.channel-volumes";
const masterMixStorageKey = "blatyrpg.audio.master-mix";
const effectMixStorageKey = "blatyrpg.audio.effect-mix";

const storedCategoryVolumes = () => {
  try {
    const value = JSON.parse(localStorage.getItem(storageKey));
    return value && typeof value === "object" ? value : {};
  } catch (_error) {
    return {};
  }
};

const storedChannelVolumes = () => {
  try {
    const value = JSON.parse(localStorage.getItem(channelVolumeStorageKey));
    return value && typeof value === "object" ? value : {};
  } catch (_error) {
    return {};
  }
};

const storedMasterMix = () => {
  try {
    const value = JSON.parse(localStorage.getItem(masterMixStorageKey));
    return value && typeof value === "object" ? value : {};
  } catch (_error) {
    return {};
  }
};

const initialChannel = (channelId, category, localVolume = 1) => ({
  channelId,
  category,
  trackId: null,
  title: "",
  status: "stopped",
  currentTime: 0,
  duration: null,
  loop: false,
  volume: 1,
  localVolume: clamp(localVolume),
  muted: false,
  sourceType: category === "external" ? "external-input" : null,
  playlistId: null,
  sourceLabel: "",
  deviceSlot: null,
  blocked: false,
  sourceUnavailable: false,
  playbackError: null,
  level: 0,
  meterAvailable: false,
});

export class AudioMixerService {
  constructor(options = {}) {
    this.AudioContext =
      options.AudioContext ||
      (typeof window === "undefined"
        ? null
        : window.AudioContext || window.webkitAudioContext);
    this.fetch =
      options.fetch ||
      (typeof window === "undefined" ? null : window.fetch.bind(window));
    this.context = null;
    this.categoryNodes = new Map();
    this.records = new Map();
    this.effectPayloads = new Map();
    this.effectBuffers = new Map();
    this.effectRecords = new Map();
    this.effectListeners = new Set();
    this.listeners = new Set();
    this.endedListeners = new Set();
    this.timers = new Map();
    this.ducking = { active: false, enabled: true, musicDb: -6, ambientDb: -4 };
    const stored =
      typeof localStorage === "undefined" ? {} : storedCategoryVolumes();
    const storedChannels =
      typeof localStorage === "undefined" ? {} : storedChannelVolumes();
    const storedMaster =
      typeof localStorage === "undefined" ? {} : storedMasterMix();
    this.masterVolume = clamp(storedMaster.volume ?? 1, 0, 1.4);
    this.masterMuted = storedMaster.muted === true;
    let storedEffectVolume = 1;
    try {
      const value = localStorage.getItem(effectMixStorageKey);
      if (value !== null) storedEffectVolume = Number(value);
    } catch (_error) {
      // A private browsing policy only disables persistence.
    }
    this.effectMasterVolume = clamp(storedEffectVolume, 0, 1);
    this.categoryVolumes = {
      voice: clamp(stored.voice ?? 1),
      music: 1,
      ambient: 1,
      sfx: 1,
      external: 1,
    };
    this.channels = Object.fromEntries(
      JUKEBOX_CHANNELS.map(([id, category]) => [
        id,
        initialChannel(
          id,
          category,
          storedChannels[id] ??
            stored[category] ??
            ({ music: 0.5, ambient: 0.4, sfx: 0.7, external: 0.7 }[category] ||
              1),
        ),
      ]),
    );
    this.refreshTimer = null;
    this.meterTimer = null;
  }

  async ensureContext() {
    if (!this.AudioContext) throw new Error("web_audio_unavailable");
    const recovery = [];
    if (this.context?.state === "closed") {
      for (const [channelId, record] of this.records) {
        const channel = this.channels[channelId];
        if (record.track || record.external) {
          recovery.push({
            channelId,
            track: record.track,
            mediaTrack: record.stream?.getAudioTracks?.()[0],
            position: this.currentTime(channelId),
            status: channel.status,
          });
        }
        await this.unloadRecord(channelId);
      }
      this.context = null;
      this.categoryNodes.clear();
    }
    if (!this.context) {
      this.context = new this.AudioContext({ latencyHint: "interactive" });
      this.context.addEventListener?.("statechange", () => this.emit());
      ["music", "ambient", "sfx", "external"].forEach((category) => {
        const node = this.context.createGain();
        node.connect(this.context.destination);
        this.categoryNodes.set(category, node);
      });
      this.applyCategoryGains(true);
    }
    for (const item of recovery) {
      if (item.mediaTrack?.readyState === "live") {
        await this.attachExternalInput(item.channelId, item.mediaTrack);
      } else if (item.track) {
        await this.load(item.channelId, item.track, item.position);
        if (item.status === "playing") {
          await this.play(item.channelId, item.position).catch(() => {});
        }
      }
    }
    this.startRefresh();
    return this.context;
  }

  async unlock() {
    const context = await this.ensureContext();
    const blockedEffects = [...this.effectRecords.values()].filter(
      (record) => record.blockedAtCreation,
    );
    if (context.state === "suspended") await context.resume();
    for (const record of blockedEffects) {
      const startedAt = Number(record.config.startedAt);
      const offset = Math.max(
        0,
        Number.isFinite(startedAt)
          ? (Date.now() - startedAt) / 1000
          : Number(record.config.offset) || 0,
      );
      this.stopEffect(record.playbackId, 0);
      await this.playEffect({ ...record.config, offset }).catch(() => {});
    }
    const attempts = [];
    for (const channelId of this.records.keys()) {
      if (
        this.channels[channelId].blocked &&
        this.channels[channelId].status === "playing"
      ) {
        attempts.push(
          this.play(channelId, this.channels[channelId].currentTime),
        );
      }
    }
    await Promise.allSettled(attempts);
    this.emit();
  }

  async preloadEffects(tracks = []) {
    return Promise.allSettled(
      tracks
        .filter((track) => track?.url)
        .map((track) => this.effectPayload(track)),
    );
  }

  async effectPayload(track) {
    const trackId = Number(track?.id);
    if (!trackId || !track?.url) throw new Error("audio_track_unavailable");
    if (this.effectPayloads.has(trackId)) {
      return this.effectPayloads.get(trackId);
    }
    const loading = (async () => {
      const token = resolveAccessToken();
      const response = await this.fetch(track.url, {
        headers: { ...(token ? { Authorization: `Bearer ${token}` } : {}) },
        credentials: "same-origin",
      });
      if (!response.ok) throw new Error(`audio_fetch_${response.status}`);
      return response.arrayBuffer();
    })();
    this.effectPayloads.set(trackId, loading);
    try {
      const payload = await loading;
      this.effectPayloads.set(trackId, payload);
      return payload;
    } catch (error) {
      if (this.effectPayloads.get(trackId) === loading) {
        this.effectPayloads.delete(trackId);
      }
      throw error;
    }
  }

  async effectBuffer(track) {
    const trackId = Number(track?.id);
    if (!trackId || !track?.url) throw new Error("audio_track_unavailable");
    if (this.effectBuffers.has(trackId)) return this.effectBuffers.get(trackId);
    const loading = (async () => {
      const context = await this.ensureContext();
      const payload = await this.effectPayload(track);
      return context.decodeAudioData(payload.slice(0));
    })();
    this.effectBuffers.set(trackId, loading);
    try {
      const buffer = await loading;
      this.effectBuffers.set(trackId, buffer);
      return buffer;
    } catch (error) {
      if (this.effectBuffers.get(trackId) === loading) {
        this.effectBuffers.delete(trackId);
      }
      throw error;
    }
  }

  async playEffect(config = {}) {
    const playbackId = String(config.playbackId || "");
    if (!playbackId) throw new TypeError("sound_effect_playback_id_required");
    const context = await this.ensureContext();
    const buffer = await this.effectBuffer(config.track);
    this.stopEffect(playbackId, 0);
    let offset = Math.max(0, Number(config.offset) || 0);
    if (config.loop && buffer.duration > 0) offset %= buffer.duration;
    if (!config.loop && offset >= buffer.duration) return false;

    const source = context.createBufferSource();
    const gain = context.createGain();
    const compressor = context.createDynamicsCompressor();
    const meter = createAudioLevelMeter(context);
    source.buffer = buffer;
    source.loop = Boolean(config.loop);
    source.connect(gain);
    gain.connect(compressor);
    if (meter) {
      compressor.connect(meter.analyser);
      meter.analyser.connect(this.categoryNodes.get("sfx"));
    } else {
      compressor.connect(this.categoryNodes.get("sfx"));
    }
    const baseVolume = clamp(config.volume, 0, 1) * this.effectMasterVolume;
    const startAt = Math.max(
      context.currentTime,
      context.currentTime + (Number(config.delayMs) || 0) / 1000,
    );
    const fadeInSeconds = Math.max(0, Number(config.fadeInMs) || 0) / 1000;
    gain.gain.setValueAtTime(fadeInSeconds > 0 ? 0.0001 : baseVolume, startAt);
    if (fadeInSeconds > 0) {
      gain.gain.exponentialRampToValueAtTime(
        Math.max(0.0001, baseVolume),
        startAt + fadeInSeconds,
      );
    }
    const remaining = buffer.duration - offset;
    const fadeOutSeconds = Math.max(0, Number(config.fadeOutMs) || 0) / 1000;
    if (!config.loop && fadeOutSeconds > 0 && remaining > 0) {
      const fadeAt = startAt + Math.max(0, remaining - fadeOutSeconds);
      gain.gain.setValueAtTime(Math.max(0.0001, baseVolume), fadeAt);
      gain.gain.exponentialRampToValueAtTime(0.0001, startAt + remaining);
    }
    const record = {
      playbackId,
      source,
      gain,
      compressor,
      ...(meter || {}),
      config: { ...config },
      track: config.track,
      startedAt: startAt - offset,
      blockedAtCreation: context.state !== "running",
      status: "playing",
      level: 0,
    };
    source.onended = () => {
      if (this.effectRecords.get(playbackId) !== record) return;
      this.releaseEffect(record);
      this.effectRecords.delete(playbackId);
      this.emitEffects();
    };
    this.effectRecords.set(playbackId, record);
    source.start(startAt, offset);
    this.startMetering();
    this.emitEffects();
    return true;
  }

  stopEffect(playbackId, fadeOutMs = 0) {
    const key = String(playbackId || "");
    const record = this.effectRecords.get(key);
    if (!record) return false;
    const milliseconds = Math.max(0, Number(fadeOutMs) || 0);
    const now = this.context?.currentTime || 0;
    record.status = milliseconds > 0 ? "stopping" : "stopped";
    try {
      if (milliseconds > 0) {
        record.gain.gain.cancelScheduledValues(now);
        record.gain.gain.setValueAtTime(
          Math.max(0.0001, record.gain.gain.value),
          now,
        );
        record.gain.gain.exponentialRampToValueAtTime(
          0.0001,
          now + milliseconds / 1000,
        );
        record.source.stop(now + milliseconds / 1000);
      } else {
        record.source.stop();
      }
    } catch (_error) {
      this.releaseEffect(record);
      this.effectRecords.delete(key);
    }
    this.emitEffects();
    return true;
  }

  setEffectVolume(playbackId, volume, transitionMs = 80) {
    const key = String(playbackId || "");
    const record = this.effectRecords.get(key);
    if (!record || record.status === "stopped") return false;
    const normalized = clamp(volume, 0, 1);
    const target = normalized * this.effectMasterVolume;
    const now = this.context?.currentTime || 0;
    const seconds = Math.max(0, Number(transitionMs) || 0) / 1000;
    record.config.volume = normalized;
    try {
      record.gain.gain.cancelScheduledValues(now);
      record.gain.gain.setValueAtTime(
        Math.max(0.0001, record.gain.gain.value),
        now,
      );
      if (seconds > 0) {
        record.gain.gain.linearRampToValueAtTime(
          Math.max(0.0001, target),
          now + seconds,
        );
      } else {
        record.gain.gain.setValueAtTime(Math.max(0.0001, target), now);
      }
    } catch (_error) {
      return false;
    }
    this.emitEffects();
    return true;
  }

  stopAllEffects(fadeOutMs = 0) {
    [...this.effectRecords.keys()].forEach((playbackId) =>
      this.stopEffect(playbackId, fadeOutMs),
    );
  }

  setEffectMasterVolume(volume) {
    this.effectMasterVolume = clamp(volume, 0, 1);
    try {
      localStorage.setItem(
        effectMixStorageKey,
        String(this.effectMasterVolume),
      );
    } catch (_error) {
      // The current listening level remains active without persistence.
    }
    for (const record of this.effectRecords.values()) {
      const value = clamp(record.config.volume, 0, 1) * this.effectMasterVolume;
      record.gain.gain.setTargetAtTime(
        value,
        this.context?.currentTime || 0,
        0.015,
      );
    }
    this.emitEffects();
  }

  subscribeEffects(listener) {
    this.effectListeners.add(listener);
    listener(this.effectSnapshot());
    return () => this.effectListeners.delete(listener);
  }

  effectSnapshot() {
    return {
      masterVolume: this.effectMasterVolume,
      instances: Object.fromEntries(
        [...this.effectRecords].map(([id, record]) => [
          id,
          {
            playbackId: id,
            slotId: Number(record.config.slotId) || null,
            trackId: Number(record.track?.id) || null,
            title: String(record.track?.title || ""),
            status: record.status,
            loop: Boolean(record.config.loop),
            level: Number(record.level) || 0,
            currentTime: Math.max(
              0,
              (this.context?.currentTime || 0) - record.startedAt,
            ),
            duration: Number(record.source?.buffer?.duration) || null,
          },
        ]),
      ),
    };
  }

  emitEffects() {
    const snapshot = this.effectSnapshot();
    this.effectListeners.forEach((listener) => listener(snapshot));
  }

  releaseEffect(record) {
    record.source.onended = null;
    [record.source, record.gain, record.compressor, record.analyser].forEach(
      (node) => {
        try {
          node?.disconnect();
        } catch (_error) {
          // A disconnected Web Audio node is already released.
        }
      },
    );
    this.stopMeteringIfIdle();
  }

  async load(channelId, track, position = 0) {
    const channel = this.requireChannel(channelId);
    await this.ensureContext();
    await this.unloadRecord(channelId);
    Object.assign(channel, {
      trackId: Number(track?.id) || null,
      title: String(track?.title || ""),
      status: "loading",
      currentTime: Math.max(0, Number(position) || 0),
      duration: Number(track?.duration) || null,
      loop: Boolean(track?.loop),
      sourceType: track?.sourceType || null,
      playlistId: Number(track?.playlistId) || null,
      sourceLabel: String(track?.sourceLabel || ""),
      deviceSlot: null,
      blocked: false,
      sourceUnavailable: false,
      playbackError: null,
    });
    this.emit();

    const provider = createAudioProvider(track, {
      hostId: `jukebox-provider-${channelId}`,
      onEnded: () => this.handleEnded(channelId),
      onBlocked: () => {
        if (this.records.get(channelId)?.provider !== provider) return;
        channel.blocked = true;
        channel.playbackError = "youtube_autoplay_blocked";
        this.emit();
      },
      onPlaying: () => {
        if (this.records.get(channelId)?.provider !== provider) return;
        channel.blocked = false;
        channel.sourceUnavailable = false;
        channel.playbackError = null;
        this.emit();
      },
      onError: (error) => {
        if (this.records.get(channelId)?.provider !== provider) return;
        channel.status = "error";
        channel.blocked = false;
        channel.sourceUnavailable = true;
        channel.playbackError = String(
          error?.code || error?.message || "youtube_player_error",
        );
        this.emit();
      },
    });
    if (provider) {
      try {
        await provider.load(channel.currentTime);
      } catch (error) {
        provider.destroy();
        channel.status = "error";
        channel.sourceUnavailable = true;
        channel.playbackError = String(
          error?.code || error?.message || "youtube_player_error",
        );
        this.emit();
        throw error;
      }
      this.records.set(channelId, { track, provider });
      channel.status = "paused";
      channel.level = 0;
      channel.meterAvailable = false;
      this.applyChannelGain(channelId);
      this.emit();
      return;
    }

    const sourceUrl = await this.materializeUrl(track);
    const element = new Audio();
    element.preload = "auto";
    element.src = sourceUrl.url;
    element.loop = channel.loop;
    const source = this.context.createMediaElementSource(element);
    const gain = this.context.createGain();
    const compressor = this.context.createDynamicsCompressor();
    const meter = createAudioLevelMeter(this.context);
    source.connect(gain);
    gain.connect(compressor);
    if (meter) {
      compressor.connect(meter.analyser);
      meter.analyser.connect(this.categoryNodes.get(channel.category));
    } else {
      compressor.connect(this.categoryNodes.get(channel.category));
    }
    const record = {
      track,
      element,
      source,
      gain,
      compressor,
      objectUrl: sourceUrl.objectUrl ? sourceUrl.url : null,
      onEnded: () => this.handleEnded(channelId),
      ...(meter || {}),
    };
    this.records.set(channelId, record);
    element.addEventListener("ended", record.onEnded);
    await this.preload(element);
    if (this.records.get(channelId) !== record) return;
    channel.duration = Number.isFinite(element.duration)
      ? element.duration
      : channel.duration;
    element.currentTime = Math.min(
      channel.currentTime,
      channel.duration || channel.currentTime,
    );
    channel.status = "paused";
    channel.meterAvailable = Boolean(meter);
    this.applyChannelGain(channelId);
    this.startMetering();
    this.emit();
  }

  async play(channelId, position = null) {
    const channel = this.requireChannel(channelId);
    const record = this.records.get(channelId);
    if (!record) return false;
    await this.ensureContext();
    if (position !== null) this.seek(channelId, position);
    channel.status = "playing";
    channel.blocked = false;
    try {
      if (record.provider) await record.provider.play(channel.currentTime);
      else await record.element.play();
    } catch (error) {
      channel.blocked = true;
      this.emit();
      throw error;
    }
    this.emit();
    return true;
  }

  pause(channelId) {
    const channel = this.requireChannel(channelId);
    const record = this.records.get(channelId);
    if (record?.provider) record.provider.pause();
    else record?.element?.pause();
    channel.currentTime = this.currentTime(channelId);
    channel.status = "paused";
    this.clearTimer(channelId);
    this.emit();
  }

  stop(channelId) {
    const channel = this.requireChannel(channelId);
    const record = this.records.get(channelId);
    if (record?.provider) record.provider.stop();
    else if (record?.element) {
      record.element.pause();
      record.element.currentTime = 0;
      record.element.playbackRate = 1;
    }
    channel.currentTime = 0;
    channel.status = "stopped";
    channel.blocked = false;
    channel.sourceUnavailable = false;
    channel.playbackError = null;
    this.clearTimer(channelId);
    this.applyChannelGain(channelId);
    this.emit();
  }

  seek(channelId, position) {
    const channel = this.requireChannel(channelId);
    const target = clamp(position, 0, channel.duration || 86400);
    const record = this.records.get(channelId);
    if (record?.provider) record.provider.seek(target);
    else if (record?.element && Number.isFinite(record.element.duration))
      record.element.currentTime = target;
    channel.currentTime = target;
    this.emit();
  }

  setLoop(channelId, loop) {
    const channel = this.requireChannel(channelId);
    channel.loop = Boolean(loop);
    const record = this.records.get(channelId);
    if (record?.provider) record.provider.setLoop(channel.loop);
    else if (record?.element) record.element.loop = channel.loop;
    this.emit();
  }

  setVolume(channelId, volume) {
    this.requireChannel(channelId).volume = clamp(volume);
    this.applyChannelGain(channelId);
    this.emit();
  }

  setMuted(channelId, muted) {
    this.requireChannel(channelId).muted = Boolean(muted);
    this.applyChannelGain(channelId);
    this.emit();
  }

  setLocalVolume(channelId, volume) {
    this.requireChannel(channelId).localVolume = clamp(volume);
    try {
      localStorage.setItem(
        channelVolumeStorageKey,
        JSON.stringify(
          Object.fromEntries(
            Object.entries(this.channels).map(([id, channel]) => [
              id,
              channel.localVolume,
            ]),
          ),
        ),
      );
    } catch (_error) {
      // Local listening preferences remain usable when storage is unavailable.
    }
    this.applyChannelGain(channelId);
    this.emit();
  }

  setMasterVolume(volume) {
    this.masterVolume = clamp(volume, 0, 1.4);
    this.persistMasterMix();
    this.applyCategoryGains();
    this.emit();
  }

  setMasterMuted(muted) {
    this.masterMuted = Boolean(muted);
    this.persistMasterMix();
    this.applyCategoryGains();
    this.emit();
  }

  persistMasterMix() {
    try {
      localStorage.setItem(
        masterMixStorageKey,
        JSON.stringify({ volume: this.masterVolume, muted: this.masterMuted }),
      );
    } catch (_error) {
      // Local listening preferences remain usable when storage is unavailable.
    }
  }

  fadeIn(channelId, durationMs, targetVolume = null) {
    const channel = this.requireChannel(channelId);
    if (targetVolume !== null) channel.volume = clamp(targetVolume);
    channel.muted = false;
    const record = this.records.get(channelId);
    if (record?.gain && this.context) {
      const now = this.context.currentTime;
      record.gain.gain.cancelScheduledValues(now);
      record.gain.gain.setValueAtTime(0.0001, now);
      record.gain.gain.exponentialRampToValueAtTime(
        Math.max(0.0001, channel.volume * channel.localVolume),
        now + Math.max(0.01, Number(durationMs) / 1000),
      );
    } else if (record?.provider) {
      this.fadeProvider(channelId, 0, channel.volume, durationMs);
    }
    this.emit();
  }

  fadeOut(channelId, durationMs) {
    const channel = this.requireChannel(channelId);
    const record = this.records.get(channelId);
    const milliseconds = Math.max(0, Number(durationMs) || 0);
    this.clearTimer(channelId);
    if (record?.gain && this.context) {
      const now = this.context.currentTime;
      record.gain.gain.cancelScheduledValues(now);
      record.gain.gain.setValueAtTime(
        Math.max(0.0001, record.gain.gain.value),
        now,
      );
      record.gain.gain.exponentialRampToValueAtTime(
        0.0001,
        now + Math.max(0.01, milliseconds / 1000),
      );
    } else if (record?.provider) {
      this.fadeProvider(channelId, channel.volume, 0, milliseconds);
    }
    this.timers.set(
      channelId,
      setTimeout(() => {
        this.timers.delete(channelId);
        this.pause(channelId);
        this.applyChannelGain(channelId);
      }, milliseconds),
    );
    channel.status = "paused";
    this.emit();
  }

  async attachExternalInput(channelId, mediaStreamTrack, metadata = {}) {
    const channel = this.requireChannel(channelId);
    await this.ensureContext();
    const rememberedLabel =
      channel.sourceType === "external-input" ? channel.sourceLabel : "";
    await this.unloadRecord(channelId);
    const stream = new MediaStream([mediaStreamTrack]);
    const source = this.context.createMediaStreamSource(stream);
    const gain = this.context.createGain();
    const compressor = this.context.createDynamicsCompressor();
    const meter = createAudioLevelMeter(this.context);
    source.connect(gain);
    gain.connect(compressor);
    if (meter) {
      compressor.connect(meter.analyser);
      meter.analyser.connect(this.categoryNodes.get("external"));
    } else {
      compressor.connect(this.categoryNodes.get("external"));
    }
    this.records.set(channelId, {
      stream,
      source,
      gain,
      compressor,
      external: true,
      ...(meter || {}),
    });
    Object.assign(channel, {
      status: "playing",
      sourceType: "external-input",
      title:
        String(metadata.label || rememberedLabel) ||
        mediaStreamTrack.label ||
        "External input",
      sourceLabel:
        String(metadata.label || rememberedLabel) ||
        mediaStreamTrack.label ||
        "External input",
      deviceSlot: metadata.deviceSlot || channel.deviceSlot || null,
      trackId: null,
      playlistId: null,
      level: 0,
      meterAvailable: Boolean(meter),
      sourceUnavailable: false,
    });
    this.applyChannelGain(channelId);
    this.startMetering();
    this.emit();
  }

  setExternalSource(channelId, source = {}) {
    const channel = this.requireChannel(channelId);
    Object.assign(channel, {
      trackId: null,
      playlistId: null,
      sourceType: "external-input",
      sourceLabel: String(source.label || ""),
      title: String(source.label || ""),
      deviceSlot: source.deviceSlot || null,
      status: source.status || "playing",
      currentTime: 0,
      sourceUnavailable: false,
    });
    this.emit();
  }

  detachExternalInput(channelId, mediaStreamTrack = null) {
    const record = this.records.get(channelId);
    if (!record?.external) return;
    if (
      mediaStreamTrack &&
      !record.stream.getTracks().includes(mediaStreamTrack)
    )
      return;
    void this.unloadRecord(channelId);
    const channel = this.requireChannel(channelId);
    channel.status = "error";
    channel.sourceUnavailable = true;
    channel.level = 0;
    channel.meterAvailable = false;
    this.emit();
  }

  setCategoryVolume(category, volume) {
    if (!(category in this.categoryVolumes)) return;
    this.categoryVolumes[category] = clamp(volume);
    try {
      localStorage.setItem(storageKey, JSON.stringify(this.categoryVolumes));
    } catch (_error) {
      // Private preferences are best-effort when storage is unavailable.
    }
    this.applyCategoryGains();
    this.emit();
  }

  setDuckingSettings(settings = {}) {
    this.ducking.enabled = settings.duckingEnabled !== false;
    this.ducking.musicDb = clamp(settings.musicDuckDb ?? -6, -24, 0);
    this.ducking.ambientDb = clamp(settings.ambientDuckDb ?? -4, -24, 0);
    this.applyCategoryGains();
  }

  setVoiceActive(active) {
    if (this.ducking.active === Boolean(active)) return;
    this.ducking.active = Boolean(active);
    this.applyCategoryGains();
  }

  async setOutputDevice(deviceId) {
    await this.ensureContext();
    if (typeof this.context.setSinkId !== "function") return false;
    await this.context.setSinkId(deviceId || "default");
    return true;
  }

  correctDrift(channelId, expectedPosition) {
    const channel = this.requireChannel(channelId);
    if (channel.status !== "playing") return;
    const actual = this.currentTime(channelId);
    const difference = Number(expectedPosition) - actual;
    if (Math.abs(difference) < 0.08) return;
    if (Math.abs(difference) >= 0.75) {
      this.seek(channelId, expectedPosition);
      return;
    }
    const record = this.records.get(channelId);
    const rate = difference > 0 ? 1.03 : 0.97;
    if (record?.provider) record.provider.playbackRate(rate);
    else if (record?.element) record.element.playbackRate = rate;
    this.clearTimer(`${channelId}:drift`);
    this.timers.set(
      `${channelId}:drift`,
      setTimeout(() => {
        this.timers.delete(`${channelId}:drift`);
        if (record?.provider) record.provider.playbackRate(1);
        else if (record?.element) record.element.playbackRate = 1;
      }, 2000),
    );
  }

  currentTime(channelId) {
    const channel = this.requireChannel(channelId);
    const record = this.records.get(channelId);
    if (record?.provider) return record.provider.currentTime();
    return Number(record?.element?.currentTime) || channel.currentTime || 0;
  }

  duration(channelId) {
    const channel = this.requireChannel(channelId);
    const record = this.records.get(channelId);
    const measured = record?.provider
      ? Number(record.provider.duration?.())
      : Number(record?.element?.duration);
    if (Number.isFinite(measured) && measured > 0) {
      channel.duration = measured;
    }
    return Number(channel.duration) || null;
  }

  async recoverPlayback() {
    const attempts = [];
    if (this.context?.state === "suspended")
      attempts.push(this.context.resume());
    for (const [channelId, record] of this.records) {
      if (this.channels[channelId]?.status !== "playing") continue;
      if (record.provider) attempts.push(record.provider.ensurePlaying?.());
      else if (record.element?.paused) attempts.push(record.element.play());
    }
    await Promise.allSettled(attempts);
    this.emit();
  }

  subscribe(listener) {
    this.listeners.add(listener);
    listener(this.snapshot());
    return () => this.listeners.delete(listener);
  }

  subscribeEnded(listener) {
    this.endedListeners.add(listener);
    return () => this.endedListeners.delete(listener);
  }

  snapshot() {
    return {
      channels: Object.fromEntries(
        Object.entries(this.channels).map(([id, channel]) => [
          id,
          {
            ...channel,
            currentTime: this.currentTime(id),
            duration: this.duration(id),
          },
        ]),
      ),
      categoryVolumes: { ...this.categoryVolumes },
      masterVolume: this.masterVolume,
      masterMuted: this.masterMuted,
      audioContextState: this.context?.state || "unavailable",
      audioBlocked: Object.values(this.channels).some(
        (channel) => channel.blocked,
      ),
      outputSelectionSupported: typeof this.context?.setSinkId === "function",
    };
  }

  async destroy() {
    this.stopAllEffects(0);
    [...this.timers.keys()].forEach((key) => this.clearTimer(key));
    for (const channelId of [...this.records.keys()])
      await this.unloadRecord(channelId);
    if (this.context && this.context.state !== "closed")
      await this.context.close().catch(() => {});
    this.context = null;
    this.categoryNodes.clear();
    this.effectPayloads.clear();
    this.effectBuffers.clear();
    this.effectRecords.clear();
    clearInterval(this.refreshTimer);
    this.refreshTimer = null;
    clearInterval(this.meterTimer);
    this.meterTimer = null;
    Object.entries(this.channels).forEach(([id, channel]) =>
      Object.assign(
        channel,
        initialChannel(id, channel.category, channel.localVolume),
      ),
    );
    this.emit();
    this.emitEffects();
  }

  requireChannel(channelId) {
    const channel = this.channels[channelId];
    if (!channel) throw new TypeError("jukebox_channel_invalid");
    return channel;
  }

  applyChannelGain(channelId) {
    const channel = this.requireChannel(channelId);
    const record = this.records.get(channelId);
    const channelVolume = channel.muted ? 0 : channel.volume;
    const volume = channelVolume * channel.localVolume;
    if (record?.gain && this.context) {
      record.gain.gain.cancelScheduledValues(this.context.currentTime);
      record.gain.gain.setTargetAtTime(volume, this.context.currentTime, 0.015);
    }
    record?.provider?.setVolume(
      this.effectiveProviderVolume(channel, channelVolume),
      channel.muted,
    );
  }

  applyCategoryGains(immediate = false) {
    if (!this.context) return;
    const masterVolume = this.masterMuted ? 0 : this.masterVolume;
    for (const category of ["music", "ambient", "sfx", "external"]) {
      let volume = this.categoryVolumes[category] * masterVolume;
      if (this.ducking.active && this.ducking.enabled) {
        if (category === "music") volume *= dbGain(this.ducking.musicDb);
        if (category === "ambient") volume *= dbGain(this.ducking.ambientDb);
      }
      const gain = this.categoryNodes.get(category)?.gain;
      if (!gain) continue;
      gain.cancelScheduledValues(this.context.currentTime);
      if (immediate) gain.setValueAtTime(volume, this.context.currentTime);
      else
        gain.setTargetAtTime(
          volume,
          this.context.currentTime,
          this.ducking.active ? 0.08 : 0.25,
        );
    }
    for (const [channelId, record] of this.records) {
      if (record.provider) this.applyChannelGain(channelId);
    }
  }

  effectiveProviderVolume(channel, channelVolume = channel.volume) {
    let categoryVolume = this.categoryVolumes[channel.category] ?? 1;
    if (this.ducking.active && this.ducking.enabled) {
      if (channel.category === "music")
        categoryVolume *= dbGain(this.ducking.musicDb);
      if (channel.category === "ambient")
        categoryVolume *= dbGain(this.ducking.ambientDb);
    }
    const masterVolume = this.masterMuted ? 0 : this.masterVolume;
    return clamp(
      clamp(channelVolume) *
        channel.localVolume *
        categoryVolume *
        masterVolume,
    );
  }

  fadeProvider(channelId, from, to, durationMs) {
    const record = this.records.get(channelId);
    const channel = this.requireChannel(channelId);
    if (!record?.provider) return;
    const timerKey = `${channelId}:provider-fade`;
    this.clearTimer(timerKey);
    const duration = Math.max(0, Number(durationMs) || 0);
    const startedAt = Date.now();
    const step = () => {
      const progress =
        duration === 0 ? 1 : clamp((Date.now() - startedAt) / duration);
      const value = Number(from) + (Number(to) - Number(from)) * progress;
      record.provider.setVolume(
        this.effectiveProviderVolume(channel, value),
        false,
      );
      if (progress >= 1 || this.records.get(channelId) !== record) {
        this.timers.delete(timerKey);
        return;
      }
      this.timers.set(timerKey, setTimeout(step, 40));
    };
    step();
  }

  handleEnded(channelId) {
    const channel = this.channels[channelId];
    if (!channel || channel.loop) return;
    channel.currentTime =
      this.duration(channelId) || this.currentTime(channelId);
    channel.status = "stopped";
    channel.blocked = false;
    this.emit();
    this.endedListeners.forEach((listener) => listener(channelId));
  }

  async materializeUrl(track) {
    if (!String(track?.url || "").startsWith("/api/"))
      return { url: track.url, objectUrl: false };
    const token = resolveAccessToken();
    const response = await this.fetch(track.url, {
      headers: { ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      credentials: "same-origin",
    });
    if (!response.ok) throw new Error(`audio_fetch_${response.status}`);
    const url = URL.createObjectURL(await response.blob());
    return { url, objectUrl: true };
  }

  preload(element) {
    return new Promise((resolve, reject) => {
      if (element.readyState >= 1) return resolve();
      const timer = setTimeout(done, 8000);
      const cleanup = () => {
        clearTimeout(timer);
        element.removeEventListener("loadedmetadata", done);
        element.removeEventListener("error", failed);
      };
      function done() {
        cleanup();
        resolve();
      }
      function failed() {
        cleanup();
        reject(new Error("audio_preload_failed"));
      }
      element.addEventListener("loadedmetadata", done, { once: true });
      element.addEventListener("error", failed, { once: true });
      element.load();
    });
  }

  async unloadRecord(channelId) {
    this.clearTimer(channelId);
    this.clearTimer(`${channelId}:drift`);
    this.clearTimer(`${channelId}:provider-fade`);
    const record = this.records.get(channelId);
    if (!record) return;
    this.records.delete(channelId);
    const channel = this.channels[channelId];
    if (channel) {
      channel.level = 0;
      channel.meterAvailable = false;
    }
    record.provider?.destroy();
    if (record.element) {
      record.element.removeEventListener("ended", record.onEnded);
      record.element.pause();
      record.element.removeAttribute("src");
      record.element.load();
    }
    [record.source, record.gain, record.compressor, record.analyser].forEach(
      (node) => {
        try {
          node?.disconnect();
        } catch (_error) {
          // A disconnected Web Audio node is already clean.
        }
      },
    );
    if (record.objectUrl) URL.revokeObjectURL(record.objectUrl);
    this.stopMeteringIfIdle();
  }

  clearTimer(key) {
    const timer = this.timers.get(key);
    if (timer) clearTimeout(timer);
    this.timers.delete(key);
  }

  startRefresh() {
    if (this.refreshTimer) return;
    this.refreshTimer = setInterval(() => {
      if (
        Object.values(this.channels).some(
          (channel) => channel.status === "playing",
        )
      )
        this.emit();
    }, 500);
  }

  startMetering() {
    if (this.meterTimer) return;
    this.meterTimer = setInterval(() => this.sampleMeters(), 50);
  }

  stopMeteringIfIdle() {
    if (
      [...this.records.values(), ...this.effectRecords.values()].some(
        (record) => record.analyser,
      )
    )
      return;
    clearInterval(this.meterTimer);
    this.meterTimer = null;
  }

  sampleMeters() {
    let changed = false;
    for (const [channelId, record] of this.records) {
      if (!record.analyser) continue;
      const level = sampleAudioLevel(record);
      const channel = this.channels[channelId];
      if (!channel || Math.abs(level - channel.level) < 0.005) continue;
      channel.level = level;
      changed = true;
    }
    let effectsChanged = false;
    for (const record of this.effectRecords.values()) {
      if (!record.analyser) continue;
      const level = sampleAudioLevel(record);
      if (Math.abs(level - record.level) < 0.005) continue;
      record.level = level;
      effectsChanged = true;
    }
    if (changed) this.emit();
    if (effectsChanged) this.emitEffects();
  }

  emit() {
    const snapshot = this.snapshot();
    this.listeners.forEach((listener) => listener(snapshot));
  }
}

export const audioMixerService = new AudioMixerService();
