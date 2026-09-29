import { BackendJukeboxError } from "./backend-jukebox-client.js";
import { createServerEvent, sendEvent } from "./protocol.js";
import { JUKEBOX_CHANNELS } from "./jukebox-protocol.js";

const category = (channelId) =>
  channelId === "music" ? "music" : channelId.startsWith("ambient") ? "ambient" : channelId === "sfx" ? "sfx" : "external";

const defaultChannel = (channelId) => ({
  channelId,
  category: category(channelId),
  trackId: null,
  status: "stopped",
  position: 0,
  duration: null,
  startedAt: null,
  executeAt: null,
  loop: false,
  volume: 1,
  muted: false,
  sourceType: channelId.startsWith("external-") ? "external-input" : null,
  playlistId: null,
  sourceLabel: "",
  deviceLabel: "",
  deviceSlot: null,
  fadeMs: 0,
});

const defaultState = () => Object.fromEntries(JUKEBOX_CHANNELS.map((id) => [id, defaultChannel(id)]));
const defaultSettings = () => ({ duckingEnabled: true, musicDuckDb: -6, ambientDuckDb: -4 });
const clone = (value) => JSON.parse(JSON.stringify(value));

const eventFor = (session, rooms, type, payload, sequence = null) =>
  createServerEvent({
    type,
    campaignId: session.campaignId,
    sequence,
    actorUserId: session.userId,
    payload,
  });

const isController = (session) => ["gm", "admin"].includes(String(session.campaignRole || "").toLowerCase());

export const createJukeboxHandler = ({ backend, rooms, onAuthenticationFailure }) => {
  const campaigns = new Map();
  const writes = new Map();

  const normalizeSnapshot = (result) => ({
    state: { ...defaultState(), ...(result?.state || {}) },
    settings: { ...defaultSettings(), ...(result?.settings || {}) },
    revision: Math.max(0, Number(result?.revision) || 0),
  });

  const ensure = async (session, force = false) => {
    const current = campaigns.get(session.campaignId);
    if (current && !force) return current;
    const loading = Promise.resolve(backend.state(session)).then(normalizeSnapshot);
    campaigns.set(session.campaignId, loading);
    try {
      const loaded = await loading;
      campaigns.set(session.campaignId, loaded);
      return loaded;
    } catch (error) {
      if (campaigns.get(session.campaignId) === loading) campaigns.delete(session.campaignId);
      throw error;
    }
  };

  const currentPosition = (channel, now = Date.now()) => {
    if (channel.status !== "playing" || !Number.isFinite(channel.startedAt)) {
      return Math.max(0, Number(channel.position) || 0);
    }
    if (Number.isFinite(channel.executeAt) && now < channel.executeAt) {
      return Math.max(0, Number(channel.position) || 0);
    }
    const duration = Number(channel.duration) || null;
    let position = Math.max(0, (now - channel.startedAt) / 1000);
    if (duration && channel.loop) position %= duration;
    else if (duration) position = Math.min(position, duration);
    return position;
  };

  const snapshot = async (session) => {
    const record = await ensure(session);
    const now = Date.now();
    const state = Object.fromEntries(
      Object.entries(record.state).map(([id, item]) => [id, { ...item, position: currentPosition(item, now) }]),
    );
    return { state, settings: record.settings, revision: record.revision, serverTime: now };
  };

  const sendState = async (session) => {
    try {
      sendEvent(session.ws, eventFor(session, rooms, "JUKEBOX_STATE", await snapshot(session)));
    } catch (error) {
      if (Number(error?.status) === 401) onAuthenticationFailure(session);
      else sendEvent(session.ws, eventFor(session, rooms, "JUKEBOX_ERROR", { code: error?.code || "jukebox_unavailable" }));
    }
  };

  const scheduledAt = (requested) => {
    const now = Date.now();
    const value = Number(requested);
    return Number.isSafeInteger(value) && value >= now + 200 && value <= now + 5000
      ? value
      : now + 500;
  };

  const apply = (record, request) => {
    const next = clone(record);
    const channel = next.state[request.channelId] || defaultChannel(request.channelId);
    const executeAt = scheduledAt(request.executeAt);
    const position = Number(request.position ?? currentPosition(channel, executeAt));
    if (request.type === "JUKEBOX_LOAD") {
      Object.assign(channel, {
        trackId: request.trackId,
        position,
        duration: request.duration,
        loop: request.loop,
        volume: request.volume,
        muted: request.muted,
        sourceType: request.sourceType,
        playlistId: request.playlistId || null,
        sourceLabel: request.sourceLabel || "",
        deviceLabel: "",
        deviceSlot: null,
        status: "paused",
        startedAt: null,
      });
    } else if (request.type === "JUKEBOX_PLAY" || request.type === "JUKEBOX_FADE_IN") {
      if (request.trackId) channel.trackId = request.trackId;
      if (request.duration !== null && request.duration !== undefined) channel.duration = request.duration;
      if (request.sourceType) channel.sourceType = request.sourceType;
      if (request.playlistId !== undefined) channel.playlistId = request.playlistId;
      if (request.sourceLabel !== undefined) channel.sourceLabel = request.sourceLabel || "";
      if (request.trackId) {
        channel.deviceLabel = "";
        channel.deviceSlot = null;
      }
      if (request.loop !== undefined) channel.loop = request.loop;
      if (request.volume !== undefined) channel.volume = request.volume;
      if (request.muted !== undefined) channel.muted = request.muted;
      channel.position = position;
      channel.status = "playing";
      channel.startedAt = executeAt - position * 1000;
      if (request.type === "JUKEBOX_FADE_IN") {
        channel.muted = false;
        channel.volume = request.volume;
        channel.fadeMs = request.fadeMs;
      }
    } else if (request.type === "JUKEBOX_DEVICE") {
      Object.assign(channel, {
        trackId: null,
        playlistId: null,
        sourceLabel: request.deviceLabel,
        deviceLabel: request.deviceLabel,
        deviceSlot: request.deviceSlot,
        sourceType: "external-input",
        position: 0,
        duration: null,
        loop: false,
        volume: request.volume,
        muted: request.muted,
        status: "playing",
        startedAt: executeAt,
      });
    } else if (request.type === "JUKEBOX_PAUSE" || request.type === "JUKEBOX_FADE_OUT") {
      channel.position = position;
      channel.status = "paused";
      channel.startedAt = null;
      if (request.type === "JUKEBOX_FADE_OUT") channel.fadeMs = request.fadeMs;
    } else if (request.type === "JUKEBOX_STOP") {
      channel.position = 0;
      channel.status = "stopped";
      channel.startedAt = null;
    } else if (request.type === "JUKEBOX_SEEK") {
      channel.position = position;
      if (channel.status === "playing") channel.startedAt = executeAt - position * 1000;
    } else if (request.type === "JUKEBOX_VOLUME") channel.volume = request.volume;
    else if (request.type === "JUKEBOX_MUTE") channel.muted = request.muted;
    else if (request.type === "JUKEBOX_LOOP") channel.loop = request.loop;
    channel.executeAt = executeAt;
    next.state[request.channelId] = channel;
    next.revision += 1;
    return { next, channel, executeAt };
  };

  const error = (session, requestId, cause) => {
    if (Number(cause?.status) === 401) {
      onAuthenticationFailure(session);
      return;
    }
    sendEvent(
      session.ws,
      eventFor(session, rooms, "JUKEBOX_ERROR", {
        requestId,
        code: cause instanceof BackendJukeboxError ? cause.code : "jukebox_unavailable",
        status: Number(cause?.status) || 503,
      }),
    );
  };

  const mutate = async (session, request) => {
    if (!isController(session)) {
      const cause = new BackendJukeboxError("jukebox_forbidden", 403);
      error(session, request.requestId, cause);
      return;
    }
    const record = await ensure(session);
    const { next } = apply(record, request);
    let saved = normalizeSnapshot(await backend.save(session, next.state, next.settings));
    let eventChannel = saved.state[request.channelId];
    if (Number(eventChannel.executeAt) < Date.now() + 100) {
      const adjusted = Date.now() + 250;
      const delta = adjusted - Number(eventChannel.executeAt || adjusted);
      eventChannel = { ...eventChannel, executeAt: adjusted };
      if (eventChannel.status === "playing" && Number.isFinite(eventChannel.startedAt)) {
        eventChannel.startedAt += delta;
      }
      saved.state[request.channelId] = eventChannel;
      saved = normalizeSnapshot(await backend.save(session, saved.state, saved.settings));
      eventChannel = saved.state[request.channelId];
    }
    campaigns.set(session.campaignId, saved);
    const sequence = rooms.nextSequence(session.campaignId);
    const payload = {
      requestId: request.requestId,
      channelId: request.channelId,
      trackId: eventChannel.trackId,
      position: eventChannel.position,
      duration: eventChannel.duration,
      executeAt: eventChannel.executeAt,
      loop: eventChannel.loop,
      volume: eventChannel.volume,
      muted: eventChannel.muted,
      sourceType: eventChannel.sourceType,
      playlistId: eventChannel.playlistId,
      sourceLabel: eventChannel.sourceLabel,
      deviceLabel: eventChannel.deviceLabel,
      deviceSlot: eventChannel.deviceSlot,
      fadeMs: eventChannel.fadeMs,
      status: eventChannel.status,
      revision: saved.revision,
      serverTime: Date.now(),
    };
    const event = eventFor(session, rooms, request.type, payload, sequence);
    for (const recipient of rooms.sessions(session.campaignId)) sendEvent(recipient.ws, event);
  };

  const orderedMutate = (session, request) => {
    const previous = writes.get(session.campaignId) || Promise.resolve();
    const current = previous.catch(() => {}).then(() => mutate(session, request));
    writes.set(session.campaignId, current);
    current.catch((cause) => error(session, request.requestId, cause)).finally(() => {
      if (writes.get(session.campaignId) === current) writes.delete(session.campaignId);
    });
  };

  const handle = (session, request) => {
    if (request.type === "JUKEBOX_SYNC") {
      sendEvent(
        session.ws,
        eventFor(session, rooms, "JUKEBOX_SYNC", {
          requestId: request.requestId,
          clientSentAt: request.clientSentAt,
          serverTime: Date.now(),
        }),
      );
      return;
    }
    orderedMutate(session, request);
  };

  return { handle, sendState, snapshot };
};
