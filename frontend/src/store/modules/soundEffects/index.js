import { soundEffectsApiClient } from "@/lib/audio/soundEffectsApiClient";
import { audioMixerService } from "@/services/audioMixerService";
import { jukeboxService } from "@/services/jukeboxService";

const uniqueId = (prefix = "sound-effect") =>
  (typeof crypto !== "undefined" && crypto.randomUUID?.()) ||
  `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;

const SOUNDPAD_JUKEBOX_CHANNEL = "sfx";
const SOUNDPAD_SOURCE_PREFIX = "soundpad:";

const usesJukeboxProvider = (slot) =>
  String(slot?.audio?.sourceType || "").toLowerCase() === "external" ||
  String(slot?.audio?.provider || "").toLowerCase() === "youtube";

const soundpadSourceLabel = (slot) =>
  `${SOUNDPAD_SOURCE_PREFIX}${Number(slot?.id)}`;

const isSoundpadJukeboxChannel = (channel) =>
  String(channel?.sourceLabel || "").startsWith(SOUNDPAD_SOURCE_PREFIX);

const state = () => ({
  campaignId: null,
  phase: "idle",
  screens: [],
  activeScreenId: null,
  tracks: [],
  libraries: { setting: [], personal: [] },
  capabilities: { canManage: false, canControl: false },
  revision: 0,
  activePlaybacks: {},
  instances: {},
  masterVolume: 1,
  previewPlaybackId: null,
  error: null,
});

let unsubscribeEffects = null;

const activeScreen = (storeState) =>
  storeState.screens.find(
    (screen) => Number(screen.id) === Number(storeState.activeScreenId),
  ) || storeState.screens[0];

const serverNow = () => jukeboxService.sync.serverNow();

const command = (context, values) =>
  context.dispatch(
    "realtime/sendSoundEffect",
    { requestId: uniqueId("request"), ...values },
    { root: true },
  );

const normalizeSnapshot = (payload = {}) => ({
  campaignId: Number(payload.campaignId),
  screens: Array.isArray(payload.screens) ? payload.screens : [],
  tracks: Array.isArray(payload.library?.items) ? payload.library.items : [],
  libraries: payload.library?.libraries || { setting: [], personal: [] },
  capabilities: payload.capabilities || {
    canManage: false,
    canControl: false,
  },
  revision: Number(payload.revision) || 0,
  activePlaybacks: Array.isArray(payload.activePlaybacks)
    ? payload.activePlaybacks
    : [],
});

const applyPlayback = async (context, playback) => {
  if (
    Number(playback.campaignId || context.state.campaignId) !==
    Number(context.state.campaignId)
  ) {
    return false;
  }
  const playbackId = String(playback.playbackId || "");
  if (!playbackId || !playback.audio?.url) return false;
  if (context.state.instances[playbackId]?.status === "playing") return true;
  if (playback.stopOthers) {
    audioMixerService.stopAllEffects(0);
    context.commit("CLEAR_PLAYBACKS");
  }
  const now = serverNow();
  const executeAt = Number(playback.executeAt || playback.startedAt || now);
  const startedAt = Number(playback.startedAt || executeAt);
  const offset = Math.max(0, (now - startedAt) / 1000);
  const duration = Number(playback.audio.duration);
  if (!playback.loop && duration > 0 && offset >= duration) {
    context.commit("REMOVE_PLAYBACK", playbackId);
    return false;
  }
  context.commit("UPSERT_PLAYBACK", playback);
  try {
    const played = await audioMixerService.playEffect({
      playbackId,
      slotId: playback.slotId,
      track: playback.audio,
      volume: Number(playback.volume ?? 1),
      loop: Boolean(playback.loop),
      fadeInMs: Number(playback.fadeInMs) || 0,
      fadeOutMs: Number(playback.fadeOutMs) || 0,
      delayMs: Math.max(0, executeAt - now),
      offset,
      startedAt,
    });
    if (!played) context.commit("REMOVE_PLAYBACK", playbackId);
    return played;
  } catch (error) {
    context.commit("SET_ERROR", error);
    context.commit("REMOVE_PLAYBACK", playbackId);
    return false;
  }
};

export default {
  namespaced: true,
  state,
  getters: {
    activeScreen,
    activeSlots(storeState, getters) {
      return getters.activeScreen?.slots || [];
    },
    playbackForSlot: (storeState) => (slotId) =>
      Object.values(storeState.activePlaybacks).find(
        (playback) => Number(playback.slotId) === Number(slotId),
      ) || null,
    instanceForSlot: (storeState) => (slotId) =>
      Object.values(storeState.instances).find(
        (instance) => Number(instance.slotId) === Number(slotId),
      ) || null,
  },
  mutations: {
    SET_CAMPAIGN(storeState, campaignId) {
      storeState.campaignId = Number(campaignId) || null;
    },
    SET_PHASE(storeState, phase) {
      storeState.phase = phase;
    },
    SET_ERROR(storeState, error) {
      storeState.error = error
        ? String(error?.code || error?.message || error)
        : null;
    },
    SET_SNAPSHOT(storeState, payload) {
      const previous = Number(storeState.activeScreenId);
      storeState.campaignId = payload.campaignId;
      storeState.screens = payload.screens;
      storeState.tracks = payload.tracks;
      storeState.libraries = payload.libraries;
      storeState.capabilities = payload.capabilities;
      storeState.revision = payload.revision;
      storeState.activePlaybacks = Object.fromEntries(
        payload.activePlaybacks.map((playback) => [
          String(playback.playbackId),
          playback,
        ]),
      );
      storeState.activeScreenId = payload.screens.some(
        (screen) => Number(screen.id) === previous,
      )
        ? previous
        : payload.screens[0]?.id || null;
      storeState.phase = "ready";
      storeState.error = null;
    },
    SET_ACTIVE_SCREEN(storeState, screenId) {
      if (
        storeState.screens.some(
          (screen) => Number(screen.id) === Number(screenId),
        )
      ) {
        storeState.activeScreenId = Number(screenId);
      }
    },
    SET_MIXER(storeState, snapshot) {
      const previous = storeState.instances;
      storeState.instances = snapshot.instances || {};
      storeState.masterVolume = Number(snapshot.masterVolume ?? 1);
      for (const playbackId of Object.keys(previous)) {
        if (
          !storeState.instances[playbackId] &&
          !storeState.activePlaybacks[playbackId]?.loop
        ) {
          delete storeState.activePlaybacks[playbackId];
        }
      }
    },
    UPSERT_PLAYBACK(storeState, playback) {
      storeState.activePlaybacks = {
        ...storeState.activePlaybacks,
        [String(playback.playbackId)]: playback,
      };
    },
    REMOVE_PLAYBACK(storeState, playbackId) {
      const next = { ...storeState.activePlaybacks };
      delete next[String(playbackId)];
      storeState.activePlaybacks = next;
    },
    CLEAR_PLAYBACKS(storeState) {
      storeState.activePlaybacks = {};
    },
    SET_PREVIEW(storeState, playbackId) {
      storeState.previewPlaybackId = playbackId || null;
    },
    RESET(storeState) {
      Object.assign(storeState, state(), {
        instances: storeState.instances,
        masterVolume: storeState.masterVolume,
      });
    },
  },
  actions: {
    initialize({ commit }) {
      if (!unsubscribeEffects) {
        unsubscribeEffects = audioMixerService.subscribeEffects((snapshot) =>
          commit("SET_MIXER", snapshot),
        );
      }
    },
    async enter(context, campaignId) {
      const id = Number(campaignId);
      context.dispatch("initialize");
      context.commit("SET_CAMPAIGN", id);
      context.commit("SET_PHASE", "loading");
      try {
        const snapshot = normalizeSnapshot(
          await soundEffectsApiClient.snapshot(id),
        );
        if (id !== Number(context.state.campaignId)) return null;
        context.commit("SET_SNAPSHOT", snapshot);
        await context.dispatch("preloadActiveScreen");
        await Promise.allSettled(
          snapshot.activePlaybacks.map((playback) =>
            applyPlayback(context, playback),
          ),
        );
        return snapshot;
      } catch (error) {
        context.commit("SET_PHASE", "error");
        context.commit("SET_ERROR", error);
        throw error;
      }
    },
    async refreshLayout(context) {
      const previousActive = context.state.activeScreenId;
      const snapshot = normalizeSnapshot(
        await soundEffectsApiClient.snapshot(context.state.campaignId),
      );
      context.commit("SET_SNAPSHOT", snapshot);
      context.commit("SET_ACTIVE_SCREEN", previousActive);
      await context.dispatch("preloadActiveScreen");
      return snapshot;
    },
    async createScreen(context, payload = {}) {
      const result = await soundEffectsApiClient.createScreen(
        context.state.campaignId,
        payload,
      );
      await context.dispatch("refreshLayout");
      context.commit("SET_ACTIVE_SCREEN", result.screen.id);
      await context.dispatch("broadcastSettings", result.revision);
      return result.screen;
    },
    async updateScreen(context, { screenId, ...payload }) {
      const result = await soundEffectsApiClient.updateScreen(
        context.state.campaignId,
        screenId,
        payload,
      );
      await context.dispatch("refreshLayout");
      await context.dispatch("broadcastSettings", result.revision);
      return result.screen;
    },
    async duplicateScreen(context, screenId) {
      const result = await soundEffectsApiClient.duplicateScreen(
        context.state.campaignId,
        screenId,
      );
      await context.dispatch("refreshLayout");
      context.commit("SET_ACTIVE_SCREEN", result.screen.id);
      await context.dispatch("broadcastSettings", result.revision);
      return result.screen;
    },
    async deleteScreen(context, screenId) {
      const result = await soundEffectsApiClient.deleteScreen(
        context.state.campaignId,
        screenId,
      );
      await context.dispatch("refreshLayout");
      await context.dispatch("broadcastSettings", result.revision);
      return result;
    },
    async saveSlot(context, { screenId, position, payload }) {
      const result = await soundEffectsApiClient.saveSlot(
        context.state.campaignId,
        screenId,
        position,
        payload,
      );
      await context.dispatch("refreshLayout");
      await context.dispatch("broadcastSettings", result.revision);
      return result.slot;
    },
    async deleteSlot(context, { screenId, position }) {
      const result = await soundEffectsApiClient.deleteSlot(
        context.state.campaignId,
        screenId,
        position,
      );
      await context.dispatch("refreshLayout");
      await context.dispatch("broadcastSettings", result.revision);
      return result;
    },
    selectScreen({ commit, dispatch }, screenId) {
      commit("SET_ACTIVE_SCREEN", screenId);
      return dispatch("preloadActiveScreen");
    },
    preloadActiveScreen({ getters }) {
      return audioMixerService.preloadEffects(
        (getters.activeScreen?.slots || [])
          .filter(
            (slot) =>
              slot.audio?.available !== false && !usesJukeboxProvider(slot),
          )
          .map((slot) => slot.audio),
      );
    },
    async toggleSlot(context, slot) {
      if (!slot?.id || !context.state.capabilities.canControl) return false;
      if (usesJukeboxProvider(slot)) {
        const channel =
          context.rootState?.jukebox?.channels?.[SOUNDPAD_JUKEBOX_CHANNEL] ||
          {};
        const sourceLabel = soundpadSourceLabel(slot);
        const sameSlotIsActive =
          channel.sourceLabel === sourceLabel &&
          !["stopped", "error"].includes(channel.status);
        await audioMixerService.unlock().catch(() => {});
        if (sameSlotIsActive) {
          return context.dispatch("jukebox/stop", SOUNDPAD_JUKEBOX_CHANNEL, {
            root: true,
          });
        }
        if (slot.stopOthers) {
          await command(context, {
            type: "SOUND_EFFECT_STOP_ALL",
            fadeOutMs: Number(slot.fadeOutMs) || 0,
          });
        }
        return context.dispatch(
          "jukebox/playTrackNow",
          {
            channelId: SOUNDPAD_JUKEBOX_CHANNEL,
            trackId: Number(slot.audioTrackId || slot.audio?.id),
            sourceLabel,
            loop: Boolean(slot.loop),
            volume: Number(slot.volume ?? 1),
          },
          { root: true },
        );
      }
      const active = Object.values(context.state.activePlaybacks).find(
        (playback) => Number(playback.slotId) === Number(slot.id),
      );
      await audioMixerService.unlock().catch(() => {});
      if (active) {
        return command(context, {
          type: "SOUND_EFFECT_STOP",
          playbackId: active.playbackId,
        });
      }
      return command(context, {
        type: "SOUND_EFFECT_PLAY",
        playbackId: uniqueId("playback"),
        slotId: Number(slot.id),
        executeAt: Math.round(serverNow() + 400),
      });
    },
    async stopAll(context, fadeOutMs = 0) {
      if (!context.state.capabilities.canControl) return false;
      const result = await command(context, {
        type: "SOUND_EFFECT_STOP_ALL",
        fadeOutMs: Number(fadeOutMs) || 0,
      });
      const channel =
        context.rootState?.jukebox?.channels?.[SOUNDPAD_JUKEBOX_CHANNEL];
      if (isSoundpadJukeboxChannel(channel)) {
        await context.dispatch("jukebox/stop", SOUNDPAD_JUKEBOX_CHANNEL, {
          root: true,
        });
      }
      return result;
    },
    broadcastSettings(context, revision) {
      return command(context, {
        type: "SOUND_EFFECT_SETTINGS",
        revision: Number(revision || context.state.revision) || 1,
      });
    },
    requestSync(context) {
      if (!context.state.campaignId) return false;
      return command(context, { type: "SOUND_EFFECT_SYNC" });
    },
    async handleRealtimeEvent(context, event) {
      if (
        event.campaignId &&
        Number(event.campaignId) !== Number(context.state.campaignId)
      ) {
        return;
      }
      if (event.type === "sync.snapshot" && event.payload?.soundEffects) {
        const active = event.payload.soundEffects.activePlaybacks || [];
        const wanted = new Set(active.map((item) => String(item.playbackId)));
        Object.keys(context.state.instances).forEach((playbackId) => {
          if (!wanted.has(playbackId)) audioMixerService.stopEffect(playbackId);
        });
        context.commit("CLEAR_PLAYBACKS");
        await Promise.allSettled(
          active.map((playback) => applyPlayback(context, playback)),
        );
        return;
      }
      const payload = event.payload || {};
      if (event.type === "SOUND_EFFECT_STATE") {
        const active = payload.activePlaybacks || [];
        const wanted = new Set(active.map((item) => String(item.playbackId)));
        Object.keys(context.state.instances).forEach((playbackId) => {
          if (!wanted.has(playbackId)) audioMixerService.stopEffect(playbackId);
        });
        context.commit("CLEAR_PLAYBACKS");
        await Promise.allSettled(
          active.map((playback) => applyPlayback(context, playback)),
        );
      } else if (event.type === "SOUND_EFFECT_PLAY") {
        await applyPlayback(context, payload);
      } else if (event.type === "SOUND_EFFECT_STOP") {
        audioMixerService.stopEffect(payload.playbackId, payload.fadeOutMs);
        context.commit("REMOVE_PLAYBACK", payload.playbackId);
      } else if (event.type === "SOUND_EFFECT_STOP_ALL") {
        audioMixerService.stopAllEffects(payload.fadeOutMs);
        context.commit("CLEAR_PLAYBACKS");
      } else if (
        event.type === "SOUND_EFFECT_SETTINGS" &&
        Number(payload.revision) > Number(context.state.revision)
      ) {
        await context.dispatch("refreshLayout").catch(() => {});
      } else if (event.type === "SOUND_EFFECT_ERROR") {
        context.commit(
          "SET_ERROR",
          payload.code || "sound_effects_unavailable",
        );
      }
    },
    async preview(context, track) {
      if (context.state.previewPlaybackId) {
        audioMixerService.stopEffect(context.state.previewPlaybackId, 100);
        context.commit("SET_PREVIEW", null);
      }
      if (!track) return;
      await audioMixerService.unlock();
      const playbackId = uniqueId("preview");
      context.commit("SET_PREVIEW", playbackId);
      await audioMixerService.playEffect({
        playbackId,
        track,
        volume: 0.8,
        loop: false,
        fadeInMs: 0,
        fadeOutMs: 150,
      });
    },
    setMasterVolume(_context, volume) {
      audioMixerService.setEffectMasterVolume(volume);
      audioMixerService.setLocalVolume(SOUNDPAD_JUKEBOX_CHANNEL, volume);
    },
    async unlock(context) {
      await audioMixerService.unlock();
      return context.dispatch("requestSync");
    },
    leave({ commit }) {
      audioMixerService.stopAllEffects(0);
      commit("RESET");
    },
  },
};
