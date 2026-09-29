import { audioApiClient } from "@/lib/audio/audioApiClient";
import { audioMixerService } from "./audioMixerService";
import { JukeboxSyncService } from "./jukeboxSyncService";

const requestId = () =>
  (typeof crypto !== "undefined" && crypto.randomUUID?.()) ||
  `jukebox-${Date.now()}-${Math.random().toString(16).slice(2)}`;

export class JukeboxService {
  constructor(options = {}) {
    this.api = options.api || audioApiClient;
    this.mixer = options.mixer || audioMixerService;
    this.errorListeners = new Set();
    this.sync =
      options.sync ||
      new JukeboxSyncService(this.mixer, {
        onError: (error) => this.emitError(error),
      });
    this.campaignId = null;
    this.tracks = [];
    this.settingTracks = [];
    this.personalTracks = [];
    this.campaignCatalog = { system: null, setting: null };
    this.playlists = [];
    this.queues = {};
    this.capabilities = { canManage: false, canControl: false };
    this.generation = 0;
  }

  async enter(campaignId) {
    const generation = ++this.generation;
    const targetCampaignId = Number(campaignId);
    this.campaignId = targetCampaignId;
    const [catalog, state] = await Promise.all([
      this.api.tracks(targetCampaignId),
      this.api.state(targetCampaignId),
    ]);
    if (
      generation !== this.generation ||
      this.campaignId !== targetCampaignId
    ) {
      return null;
    }
    this.applyCatalog(catalog);
    this.capabilities = {
      canManage: catalog.capabilities?.canManage === true,
      canControl: state.capabilities?.canControl === true,
    };
    this.sync.setCatalog(this.tracks);
    await this.sync.applyState(state);
    return {
      tracks: this.tracks,
      settingTracks: this.settingTracks,
      personalTracks: this.personalTracks,
      campaignCatalog: this.campaignCatalog,
      playlists: this.playlists,
      queues: this.queues,
      capabilities: this.capabilities,
      upload: catalog.upload,
    };
  }

  handleRealtime(event) {
    this.sync.handle(event);
  }

  subscribeErrors(listener) {
    this.errorListeners.add(listener);
    return () => this.errorListeners.delete(listener);
  }

  emitError(error) {
    this.errorListeners.forEach((listener) => listener(error));
  }

  command(type, channelId, values = {}) {
    if (!this.capabilities.canControl) throw new Error("jukebox_forbidden");
    return {
      type,
      requestId: requestId(),
      channelId,
      executeAt: Math.round(this.sync.serverNow() + 600),
      ...values,
    };
  }

  syncRequest() {
    const id = requestId();
    return this.sync.createSyncRequest(id);
  }

  async upload(file, metadata) {
    const result = await this.api.upload(this.campaignId, file, metadata);
    this.upsert(result.track);
    return result.track;
  }

  async attach(trackId) {
    const result = await this.api.attach(this.campaignId, trackId);
    this.upsert(result.track);
    return result.track;
  }

  async addExternal(payload) {
    const result = await this.api.external(this.campaignId, payload);
    this.upsert(result.track);
    return result.track;
  }

  async remove(trackId) {
    await this.api.remove(this.campaignId, trackId);
    this.markAttached(trackId, false);
    this.sync.setCatalog(this.tracks);
  }

  async deletePersonal(trackId) {
    await this.api.deletePersonal(this.campaignId, trackId);
    this.tracks = this.tracks.filter(
      (track) => Number(track.id) !== Number(trackId),
    );
    this.personalTracks = this.personalTracks.filter(
      (track) => Number(track.id) !== Number(trackId),
    );
    this.sync.setCatalog(this.tracks);
  }

  async updatePersonal(trackId, payload) {
    const result = await this.api.updatePersonal(
      this.campaignId,
      trackId,
      payload,
    );
    this.upsert(result.track);
    return result.track;
  }

  async createPlaylist(name) {
    const result = await this.api.createPlaylist(this.campaignId, name);
    this.upsertPlaylist(result.playlist);
    return result.playlist;
  }

  async renamePlaylist(playlistId, name) {
    const result = await this.api.renamePlaylist(
      this.campaignId,
      playlistId,
      name,
    );
    this.upsertPlaylist(result.playlist);
    return result.playlist;
  }

  async deletePlaylist(playlistId) {
    await this.api.deletePlaylist(this.campaignId, playlistId);
    this.playlists = this.playlists.filter(
      (item) => Number(item.id) !== Number(playlistId),
    );
  }

  async addPlaylistItem(playlistId, trackId) {
    const result = await this.api.addPlaylistItem(
      this.campaignId,
      playlistId,
      trackId,
    );
    this.upsertPlaylist(result.playlist);
    return result.playlist;
  }

  async movePlaylistItem(playlistId, itemId, position) {
    const result = await this.api.movePlaylistItem(
      this.campaignId,
      playlistId,
      itemId,
      position,
    );
    this.upsertPlaylist(result.playlist);
    return result.playlist;
  }

  async removePlaylistItem(playlistId, itemId) {
    const result = await this.api.removePlaylistItem(
      this.campaignId,
      playlistId,
      itemId,
    );
    this.upsertPlaylist(result.playlist);
    return result.playlist;
  }

  async addQueueTrack(channelId, trackId) {
    const result = await this.api.addQueueTrack(
      this.campaignId,
      channelId,
      trackId,
    );
    this.queues = { ...this.queues, [channelId]: result.queue || [] };
    return result.queue || [];
  }

  async addQueuePlaylist(channelId, playlistId) {
    const result = await this.api.addQueuePlaylist(
      this.campaignId,
      channelId,
      playlistId,
    );
    this.queues = { ...this.queues, [channelId]: result.queue || [] };
    return result.queue || [];
  }

  async startPlaylist(channelId, playlistId) {
    const result = await this.api.startPlaylist(
      this.campaignId,
      channelId,
      playlistId,
    );
    this.queues = { ...this.queues, [channelId]: result.queue || [] };
    return result;
  }

  async moveQueueItem(channelId, itemId, position) {
    const result = await this.api.moveQueueItem(
      this.campaignId,
      channelId,
      itemId,
      position,
    );
    this.queues = { ...this.queues, [channelId]: result.queue || [] };
    return result.queue || [];
  }

  async removeQueueItem(channelId, itemId) {
    const result = await this.api.removeQueueItem(
      this.campaignId,
      channelId,
      itemId,
    );
    this.queues = { ...this.queues, [channelId]: result.queue || [] };
    return result.queue || [];
  }

  async clearQueue(channelId) {
    const result = await this.api.clearQueue(this.campaignId, channelId);
    this.queues = { ...this.queues, [channelId]: result.queue || [] };
    return result.queue || [];
  }

  upsertPlaylist(playlist) {
    this.playlists = [
      ...this.playlists.filter(
        (item) => Number(item.id) !== Number(playlist.id),
      ),
      playlist,
    ].sort((left, right) => left.name.localeCompare(right.name));
  }

  upsert(track) {
    this.tracks = [
      ...this.tracks.filter((item) => Number(item.id) !== Number(track.id)),
      track,
    ];
    const collection =
      track.library?.scope === "system" ? "settingTracks" : "personalTracks";
    this[collection] = [
      ...this[collection].filter(
        (item) => Number(item.id) !== Number(track.id),
      ),
      track,
    ];
    this.sync.setCatalog(this.tracks);
  }

  applyCatalog(catalog = {}) {
    this.tracks = Array.isArray(catalog.items) ? catalog.items : [];
    this.settingTracks = Array.isArray(catalog.libraries?.setting)
      ? catalog.libraries.setting
      : this.tracks.filter((track) => track.library?.scope === "system");
    this.personalTracks = Array.isArray(catalog.libraries?.personal)
      ? catalog.libraries.personal
      : this.tracks.filter((track) => track.library?.scope === "personal");
    this.campaignCatalog = catalog.campaignCatalog || {
      system: null,
      setting: null,
    };
    this.playlists = Array.isArray(catalog.playlists) ? catalog.playlists : [];
    this.queues = catalog.queues || {};
  }

  markAttached(trackId, attached) {
    const update = (track) =>
      Number(track.id) === Number(trackId) ? { ...track, attached } : track;
    this.tracks = this.tracks.map(update);
    this.settingTracks = this.settingTracks.map(update);
    this.personalTracks = this.personalTracks.map(update);
  }

  async leave() {
    this.generation += 1;
    this.campaignId = null;
    this.tracks = [];
    this.settingTracks = [];
    this.personalTracks = [];
    this.campaignCatalog = { system: null, setting: null };
    this.playlists = [];
    this.queues = {};
    this.capabilities = { canManage: false, canControl: false };
    this.sync.destroy();
    await this.mixer.destroy();
  }
}

export const jukeboxService = new JukeboxService();
