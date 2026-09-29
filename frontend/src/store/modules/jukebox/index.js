import { jukeboxService } from "@/services/jukeboxService";
import { audioMixerService } from "@/services/audioMixerService";

const state = () => ({
  campaignId: null,
  phase: "idle",
  tracks: [],
  settingTracks: [],
  personalTracks: [],
  playlists: [],
  queues: {},
  campaignCatalog: { system: null, setting: null },
  upload: null,
  capabilities: { canManage: false, canControl: false },
  channels: {},
  categoryVolumes: {},
  masterVolume: 1,
  masterMuted: false,
  audioContextState: "unavailable",
  audioBlocked: false,
  outputSelectionSupported: false,
  error: null,
});

let unsubscribe = null;
let unsubscribeErrors = null;
let unsubscribeEnded = null;

const trackFor = (context, trackId) =>
  context.state.tracks.find((track) => Number(track.id) === Number(trackId));

const currentChannel = (context, channelId) =>
  context.state.channels[channelId] || {};

const send = async (context, command) => {
  void audioMixerService.unlock().catch(() => {});
  const sent = await context.dispatch("realtime/sendJukebox", command, {
    root: true,
  });
  if (!sent) throw new Error("realtime_not_connected");
  return command;
};

export default {
  namespaced: true,
  state,
  mutations: {
    SET_PHASE(state, phase) {
      state.phase = phase;
    },
    SET_ERROR(state, error) {
      state.error = error
        ? String(error?.code || error?.message || error)
        : null;
    },
    SET_CATALOG(state, payload) {
      state.campaignId = Number(payload.campaignId);
      state.tracks = payload.tracks;
      state.settingTracks = payload.settingTracks || [];
      state.personalTracks = payload.personalTracks || [];
      state.playlists = payload.playlists || [];
      state.queues = payload.queues || {};
      state.campaignCatalog = payload.campaignCatalog || {
        system: null,
        setting: null,
      };
      state.capabilities = payload.capabilities;
      state.upload = payload.upload;
      state.phase = "ready";
    },
    UPSERT_TRACK(state, track) {
      state.tracks = [
        ...state.tracks.filter((item) => Number(item.id) !== Number(track.id)),
        track,
      ];
      const key =
        track.library?.scope === "system" ? "settingTracks" : "personalTracks";
      state[key] = [
        ...state[key].filter((item) => Number(item.id) !== Number(track.id)),
        track,
      ];
    },
    SET_TRACK_ATTACHED(state, { trackId, attached }) {
      const update = (track) =>
        Number(track.id) === Number(trackId) ? { ...track, attached } : track;
      state.tracks = state.tracks.map(update);
      state.settingTracks = state.settingTracks.map(update);
      state.personalTracks = state.personalTracks.map(update);
    },
    REMOVE_TRACK(state, trackId) {
      state.tracks = state.tracks.filter(
        (item) => Number(item.id) !== Number(trackId),
      );
      state.personalTracks = state.personalTracks.filter(
        (item) => Number(item.id) !== Number(trackId),
      );
    },
    UPSERT_PLAYLIST(state, playlist) {
      state.playlists = [
        ...state.playlists.filter(
          (item) => Number(item.id) !== Number(playlist.id),
        ),
        playlist,
      ].sort((left, right) => left.name.localeCompare(right.name));
    },
    REMOVE_PLAYLIST(state, playlistId) {
      state.playlists = state.playlists.filter(
        (item) => Number(item.id) !== Number(playlistId),
      );
    },
    SET_QUEUE(state, { channelId, queue }) {
      state.queues = { ...state.queues, [channelId]: queue || [] };
    },
    SET_MIXER(state, snapshot) {
      state.channels = snapshot.channels;
      state.categoryVolumes = snapshot.categoryVolumes;
      state.masterVolume = snapshot.masterVolume;
      state.masterMuted = snapshot.masterMuted;
      state.audioContextState = snapshot.audioContextState;
      state.audioBlocked = snapshot.audioBlocked;
      state.outputSelectionSupported = snapshot.outputSelectionSupported;
    },
    RESET(state) {
      Object.assign(state, {
        campaignId: null,
        phase: "idle",
        tracks: [],
        settingTracks: [],
        personalTracks: [],
        playlists: [],
        queues: {},
        campaignCatalog: { system: null, setting: null },
        upload: null,
        capabilities: { canManage: false, canControl: false },
        error: null,
      });
    },
  },
  actions: {
    initialize({ commit, dispatch, state }) {
      if (!unsubscribe)
        unsubscribe = audioMixerService.subscribe((value) =>
          commit("SET_MIXER", value),
        );
      if (!unsubscribeErrors)
        unsubscribeErrors = jukeboxService.subscribeErrors((error) =>
          commit("SET_ERROR", error),
        );
      if (!unsubscribeEnded)
        unsubscribeEnded = audioMixerService.subscribeEnded((channelId) => {
          if (state.capabilities.canControl && state.campaignId) {
            void dispatch("advanceQueue", channelId).catch(() => {});
          }
        });
    },
    async enter({ commit, dispatch }, campaignId) {
      dispatch("initialize");
      commit("SET_CATALOG", {
        campaignId,
        tracks: [],
        settingTracks: [],
        personalTracks: [],
        playlists: [],
        queues: {},
        campaignCatalog: { system: null, setting: null },
        capabilities: { canManage: false, canControl: false },
        upload: null,
      });
      commit("SET_PHASE", "loading");
      commit("SET_ERROR", null);
      try {
        const result = await jukeboxService.enter(campaignId);
        if (!result) return;
        commit("SET_CATALOG", { campaignId, ...result });
        dispatch("syncClock").catch(() => {});
      } catch (error) {
        commit("SET_PHASE", "error");
        commit("SET_ERROR", error);
        throw error;
      }
    },
    async leave({ commit }) {
      await jukeboxService.leave();
      commit("RESET");
    },
    handleRealtimeEvent({ commit }, event) {
      if (event.type === "JUKEBOX_ERROR") {
        commit("SET_ERROR", event.payload?.code || "jukebox_unavailable");
        return;
      }
      commit("SET_ERROR", null);
      jukeboxService.handleRealtime(event);
    },
    syncClock(context) {
      return send(context, jukeboxService.syncRequest());
    },
    async load(
      context,
      { channelId, trackId, playlistId = null, sourceLabel = "" },
    ) {
      let track = trackFor(context, trackId);
      if (!track) throw new Error("audio_track_not_found");
      if (!track.attached) {
        track = await jukeboxService.attach(track.id);
        context.commit("UPSERT_TRACK", track);
      }
      return send(
        context,
        jukeboxService.command("JUKEBOX_LOAD", channelId, {
          trackId: Number(track.id),
          position: 0,
          duration:
            track.duration === null ? undefined : Number(track.duration),
          loop: Boolean(track.loop),
          volume: Number(currentChannel(context, channelId).volume ?? 1),
          muted: Boolean(currentChannel(context, channelId).muted),
          sourceType: track.sourceType,
          playlistId: playlistId ? Number(playlistId) : null,
          sourceLabel: String(sourceLabel || ""),
        }),
      );
    },
    play(context, channelId) {
      const channel = currentChannel(context, channelId);
      const track = trackFor(context, channel.trackId);
      if (!track) throw new Error("audio_track_not_loaded");
      return send(
        context,
        jukeboxService.command("JUKEBOX_PLAY", channelId, {
          trackId: Number(track.id),
          position: Number(channel.currentTime) || 0,
          duration:
            track.duration === null ? undefined : Number(track.duration),
          sourceType: track.sourceType,
          playlistId: channel.playlistId || null,
          sourceLabel: channel.sourceLabel || "",
        }),
      );
    },
    pause(context, channelId) {
      const channel = currentChannel(context, channelId);
      return send(
        context,
        jukeboxService.command("JUKEBOX_PAUSE", channelId, {
          position: Number(channel.currentTime) || 0,
        }),
      );
    },
    async stop(context, channelId) {
      if (currentChannel(context, channelId).sourceType === "external-input") {
        await context.dispatch("voice/unpublishExternalInput", channelId, {
          root: true,
        });
      }
      return send(context, jukeboxService.command("JUKEBOX_STOP", channelId));
    },
    seek(context, { channelId, position }) {
      return send(
        context,
        jukeboxService.command("JUKEBOX_SEEK", channelId, {
          position: Number(position) || 0,
        }),
      );
    },
    volume(context, { channelId, volume }) {
      return send(
        context,
        jukeboxService.command("JUKEBOX_VOLUME", channelId, {
          volume: Number(volume),
        }),
      );
    },
    mute(context, { channelId, muted }) {
      return send(
        context,
        jukeboxService.command("JUKEBOX_MUTE", channelId, {
          muted: Boolean(muted),
        }),
      );
    },
    loop(context, { channelId, loop }) {
      return send(
        context,
        jukeboxService.command("JUKEBOX_LOOP", channelId, {
          loop: Boolean(loop),
        }),
      );
    },
    fadeIn(context, { channelId, fadeMs = 1500 }) {
      const channel = currentChannel(context, channelId);
      const track = trackFor(context, channel.trackId);
      if (!track) throw new Error("audio_track_not_loaded");
      return send(
        context,
        jukeboxService.command("JUKEBOX_FADE_IN", channelId, {
          position: Number(channel.currentTime) || 0,
          duration:
            track.duration === null ? undefined : Number(track.duration),
          volume: Number(channel.volume ?? 1),
          fadeMs: Number(fadeMs),
        }),
      );
    },
    fadeOut(context, { channelId, fadeMs = 1500 }) {
      return send(
        context,
        jukeboxService.command("JUKEBOX_FADE_OUT", channelId, {
          position: Number(currentChannel(context, channelId).currentTime) || 0,
          fadeMs: Number(fadeMs),
        }),
      );
    },
    setCategoryVolume(_context, { category, volume }) {
      audioMixerService.setCategoryVolume(category, volume);
    },
    setLocalChannelVolume(_context, { channelId, volume }) {
      audioMixerService.setLocalVolume(channelId, volume);
    },
    previewChannelVolume(_context, { channelId, volume }) {
      audioMixerService.setVolume(channelId, volume);
    },
    previewSeek(_context, { channelId, position }) {
      audioMixerService.seek(channelId, position);
    },
    setMasterVolume(_context, volume) {
      audioMixerService.setMasterVolume(volume);
    },
    setMasterMuted(_context, muted) {
      audioMixerService.setMasterMuted(muted);
    },
    unlockAudio() {
      return audioMixerService.unlock();
    },
    async upload({ commit }, { file, metadata }) {
      try {
        const track = await jukeboxService.upload(file, metadata);
        commit("UPSERT_TRACK", track);
        commit("SET_ERROR", null);
        return track;
      } catch (error) {
        commit("SET_ERROR", error);
        throw error;
      }
    },
    async addExternal({ commit }, payload) {
      try {
        const track = await jukeboxService.addExternal(payload);
        commit("UPSERT_TRACK", track);
        commit("SET_ERROR", null);
        return track;
      } catch (error) {
        commit("SET_ERROR", error);
        throw error;
      }
    },
    async remove({ commit }, trackId) {
      try {
        await jukeboxService.remove(trackId);
        commit("SET_TRACK_ATTACHED", { trackId, attached: false });
        commit("SET_ERROR", null);
      } catch (error) {
        commit("SET_ERROR", error);
        throw error;
      }
    },
    async attach({ commit }, trackId) {
      try {
        const track = await jukeboxService.attach(trackId);
        commit("UPSERT_TRACK", track);
        commit("SET_ERROR", null);
        return track;
      } catch (error) {
        commit("SET_ERROR", error);
        throw error;
      }
    },
    async deletePersonal({ commit }, trackId) {
      try {
        await jukeboxService.deletePersonal(trackId);
        commit("REMOVE_TRACK", trackId);
        commit("SET_ERROR", null);
      } catch (error) {
        commit("SET_ERROR", error);
        throw error;
      }
    },
    async updatePersonal({ commit }, { trackId, ...payload }) {
      try {
        const track = await jukeboxService.updatePersonal(trackId, payload);
        commit("UPSERT_TRACK", track);
        commit("SET_ERROR", null);
        return track;
      } catch (error) {
        commit("SET_ERROR", error);
        throw error;
      }
    },
    async playTrackNow(context, payload) {
      const { channelId, trackId } = payload;
      let track = trackFor(context, trackId);
      if (!track) throw new Error("audio_track_not_found");
      if (!track.attached) {
        track = await jukeboxService.attach(track.id);
        context.commit("UPSERT_TRACK", track);
      }
      const duplicate = Object.entries(context.state.channels).find(
        ([id, channel]) =>
          id !== channelId && Number(channel.trackId) === Number(trackId),
      );
      if (duplicate) await context.dispatch("stop", duplicate[0]);
      if (currentChannel(context, channelId).sourceType === "external-input") {
        await context.dispatch("voice/unpublishExternalInput", channelId, {
          root: true,
        });
      }
      return send(
        context,
        jukeboxService.command("JUKEBOX_PLAY", channelId, {
          trackId: Number(track.id),
          position: 0,
          duration:
            track.duration === null ? undefined : Number(track.duration),
          sourceType: track.sourceType,
          loop:
            payload.loop === undefined
              ? payload.playlistId
                ? false
                : Boolean(track.loop)
              : Boolean(payload.loop),
          volume:
            payload.volume === undefined
              ? Number(currentChannel(context, channelId).volume ?? 1)
              : Number(payload.volume),
          muted: Boolean(currentChannel(context, channelId).muted),
          playlistId: payload.playlistId ? Number(payload.playlistId) : null,
          sourceLabel: String(payload.sourceLabel || ""),
        }),
      );
    },
    async addQueueTrack({ commit }, { channelId, trackId }) {
      try {
        const queue = await jukeboxService.addQueueTrack(channelId, trackId);
        commit("SET_QUEUE", { channelId, queue });
        commit("SET_ERROR", null);
        return queue;
      } catch (error) {
        commit("SET_ERROR", error);
        throw error;
      }
    },
    async playQueueItem(context, { channelId, item }) {
      await context.dispatch("playTrackNow", {
        channelId,
        trackId: item.trackId,
        playlistId: item.playlistId,
        sourceLabel: item.playlistName || "",
      });
      const queue = await jukeboxService.removeQueueItem(channelId, item.id);
      context.commit("SET_QUEUE", { channelId, queue });
      return item;
    },
    async advanceQueue(context, channelId) {
      const item = context.state.queues[channelId]?.[0];
      if (!item) return context.dispatch("stop", channelId);
      return context.dispatch("playQueueItem", { channelId, item });
    },
    async moveQueueItem({ commit }, { channelId, itemId, position }) {
      const queue = await jukeboxService.moveQueueItem(
        channelId,
        itemId,
        position,
      );
      commit("SET_QUEUE", { channelId, queue });
      return queue;
    },
    async removeQueueItem({ commit }, { channelId, itemId }) {
      const queue = await jukeboxService.removeQueueItem(channelId, itemId);
      commit("SET_QUEUE", { channelId, queue });
      return queue;
    },
    async clearQueue({ commit }, channelId) {
      const queue = await jukeboxService.clearQueue(channelId);
      commit("SET_QUEUE", { channelId, queue });
      return queue;
    },
    async createPlaylist({ commit }, name) {
      const playlist = await jukeboxService.createPlaylist(name);
      commit("UPSERT_PLAYLIST", playlist);
      return playlist;
    },
    async renamePlaylist({ commit }, { playlistId, name }) {
      const playlist = await jukeboxService.renamePlaylist(playlistId, name);
      commit("UPSERT_PLAYLIST", playlist);
      return playlist;
    },
    async deletePlaylist({ commit }, playlistId) {
      await jukeboxService.deletePlaylist(playlistId);
      commit("REMOVE_PLAYLIST", playlistId);
    },
    async addPlaylistItem({ commit }, { playlistId, trackId }) {
      const playlist = await jukeboxService.addPlaylistItem(
        playlistId,
        trackId,
      );
      commit("UPSERT_PLAYLIST", playlist);
      return playlist;
    },
    async movePlaylistItem({ commit }, { playlistId, itemId, position }) {
      const playlist = await jukeboxService.movePlaylistItem(
        playlistId,
        itemId,
        position,
      );
      commit("UPSERT_PLAYLIST", playlist);
      return playlist;
    },
    async removePlaylistItem({ commit }, { playlistId, itemId }) {
      const playlist = await jukeboxService.removePlaylistItem(
        playlistId,
        itemId,
      );
      commit("UPSERT_PLAYLIST", playlist);
      return playlist;
    },
    async addPlaylistToQueue({ commit }, { channelId, playlistId }) {
      const queue = await jukeboxService.addQueuePlaylist(
        channelId,
        playlistId,
      );
      commit("SET_QUEUE", { channelId, queue });
      return queue;
    },
    async startPlaylist(context, { channelId, playlistId }) {
      const result = await jukeboxService.startPlaylist(channelId, playlistId);
      context.commit("SET_QUEUE", { channelId, queue: result.queue });
      await context.dispatch("playTrackNow", {
        channelId,
        trackId: result.current.trackId,
        playlistId,
        sourceLabel: result.playlist.name,
      });
      return result;
    },
    async activateDevice(context, { channelId, deviceSlot, deviceId, label }) {
      if (!deviceId) throw new Error("audio_input_device_required");
      if (
        !["connected", "reconnecting"].includes(context.rootState.voice.status)
      ) {
        await context.dispatch("voice/join", context.state.campaignId, {
          root: true,
        });
        await context.dispatch("voice/setMuted", true, { root: true });
      }
      if (currentChannel(context, channelId).sourceType === "external-input") {
        await context.dispatch("voice/unpublishExternalInput", channelId, {
          root: true,
        });
      }
      await context.dispatch(
        "voice/publishExternalInput",
        { channelId, deviceId, deviceSlot, label },
        { root: true },
      );
      return send(
        context,
        jukeboxService.command("JUKEBOX_DEVICE", channelId, {
          deviceLabel: label,
          deviceSlot,
          volume: Number(currentChannel(context, channelId).volume ?? 1),
          muted: Boolean(currentChannel(context, channelId).muted),
        }),
      );
    },
  },
};
