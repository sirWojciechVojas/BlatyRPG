import {
  JsonApiError,
  jsonApiClient,
  resolveAccessToken,
} from "@/lib/api/jsonApiClient";

const campaignPath = (campaignId, suffix) =>
  `campaigns/${Number(campaignId)}/audio${suffix}`;

const uploadErrorCode = (payload, fallback) =>
  String(payload?.code || payload?.message || fallback);

const normalizeDeviceSettings = (payload = {}) => {
  const settings = payload?.settings ?? payload?.data?.settings ?? payload;
  const external = settings?.externalInputs || {};
  return {
    microphoneDeviceId: String(settings?.microphoneDeviceId || ""),
    outputDeviceId: String(settings?.outputDeviceId || ""),
    externalInputs: {
      "external-1": String(external["external-1"] || ""),
      "external-2": String(external["external-2"] || ""),
    },
  };
};

export const createAudioApiClient = (options = {}) => {
  const api = options.api || jsonApiClient;
  const fetchImpl =
    options.fetchImpl ||
    (() => (typeof window === "undefined" ? null : window.fetch.bind(window)));
  const tokenResolver = options.tokenResolver || resolveAccessToken;
  const baseUrl = String(
    options.baseUrl || process.env.VUE_APP_API_BASE || "/api",
  ).replace(/\/+$/u, "");

  return {
    async deviceSettings() {
      return normalizeDeviceSettings(
        await api.request("auth/audio-device-settings"),
      );
    },
    async saveDeviceSettings(settings) {
      const normalized = normalizeDeviceSettings(settings);
      return normalizeDeviceSettings(
        await api.request("auth/audio-device-settings", {
          method: "PUT",
          body: normalized,
        }),
      );
    },
    token: (campaignId) =>
      api.request(`campaigns/${Number(campaignId)}/voice/token`, {
        method: "POST",
      }),
    tracks: (campaignId) => api.request(campaignPath(campaignId, "/tracks")),
    state: (campaignId) =>
      api.request(`campaigns/${Number(campaignId)}/jukebox`),
    external: (campaignId, payload) =>
      api.request(campaignPath(campaignId, "/tracks/external"), {
        method: "POST",
        body: payload,
      }),
    attach: (campaignId, trackId) =>
      api.request(campaignPath(campaignId, `/tracks/${Number(trackId)}`), {
        method: "PUT",
      }),
    remove: (campaignId, trackId) =>
      api.request(campaignPath(campaignId, `/tracks/${Number(trackId)}`), {
        method: "DELETE",
      }),
    deletePersonal: (campaignId, trackId) =>
      api.request(
        campaignPath(campaignId, `/library/tracks/${Number(trackId)}`),
        { method: "DELETE" },
      ),
    updatePersonal: (campaignId, trackId, payload) =>
      api.request(
        campaignPath(campaignId, `/library/tracks/${Number(trackId)}`),
        { method: "PATCH", body: payload },
      ),
    createPlaylist: (campaignId, name) =>
      api.request(campaignPath(campaignId, "/playlists"), {
        method: "POST",
        body: { name },
      }),
    renamePlaylist: (campaignId, playlistId, name) =>
      api.request(
        campaignPath(campaignId, `/playlists/${Number(playlistId)}`),
        { method: "PATCH", body: { name } },
      ),
    deletePlaylist: (campaignId, playlistId) =>
      api.request(
        campaignPath(campaignId, `/playlists/${Number(playlistId)}`),
        { method: "DELETE" },
      ),
    addPlaylistItem: (campaignId, playlistId, trackId) =>
      api.request(
        campaignPath(campaignId, `/playlists/${Number(playlistId)}/items`),
        { method: "POST", body: { trackId: Number(trackId) } },
      ),
    movePlaylistItem: (campaignId, playlistId, itemId, position) =>
      api.request(
        campaignPath(
          campaignId,
          `/playlists/${Number(playlistId)}/items/${Number(itemId)}`,
        ),
        { method: "PATCH", body: { position: Number(position) } },
      ),
    removePlaylistItem: (campaignId, playlistId, itemId) =>
      api.request(
        campaignPath(
          campaignId,
          `/playlists/${Number(playlistId)}/items/${Number(itemId)}`,
        ),
        { method: "DELETE" },
      ),
    addQueueTrack: (campaignId, channelId, trackId) =>
      api.request(campaignPath(campaignId, `/queues/${channelId}/tracks`), {
        method: "POST",
        body: { trackId: Number(trackId) },
      }),
    addQueuePlaylist: (campaignId, channelId, playlistId) =>
      api.request(
        campaignPath(
          campaignId,
          `/queues/${channelId}/playlists/${Number(playlistId)}`,
        ),
        { method: "POST" },
      ),
    startPlaylist: (campaignId, channelId, playlistId) =>
      api.request(
        campaignPath(
          campaignId,
          `/queues/${channelId}/playlists/${Number(playlistId)}/start`,
        ),
        { method: "POST" },
      ),
    moveQueueItem: (campaignId, channelId, itemId, position) =>
      api.request(
        campaignPath(
          campaignId,
          `/queues/${channelId}/items/${Number(itemId)}`,
        ),
        { method: "PATCH", body: { position: Number(position) } },
      ),
    removeQueueItem: (campaignId, channelId, itemId) =>
      api.request(
        campaignPath(
          campaignId,
          `/queues/${channelId}/items/${Number(itemId)}`,
        ),
        { method: "DELETE" },
      ),
    clearQueue: (campaignId, channelId) =>
      api.request(campaignPath(campaignId, `/queues/${channelId}`), {
        method: "DELETE",
      }),
    async upload(campaignId, file, metadata = {}) {
      const fetcher = fetchImpl();
      if (!fetcher) throw new JsonApiError("network_error", { network: true });
      const body = new FormData();
      body.append("file", file, file.name);
      Object.entries(metadata).forEach(([key, value]) => {
        if (value === null || value === undefined) return;
        body.append(
          key,
          Array.isArray(value) ? JSON.stringify(value) : String(value),
        );
      });
      const token = tokenResolver();
      let response;
      try {
        response = await fetcher(
          `${baseUrl}/${campaignPath(campaignId, "/tracks/upload")}`,
          {
            method: "POST",
            headers: token ? { Authorization: `Bearer ${token}` } : {},
            credentials: "same-origin",
            body,
          },
        );
      } catch (cause) {
        throw new JsonApiError("network_error", {
          network: true,
          payload: cause,
        });
      }
      const payload = await response.json().catch(() => null);
      if (!response.ok) {
        throw new JsonApiError(
          uploadErrorCode(payload, `http_${response.status}`),
          {
            status: response.status,
            payload,
          },
        );
      }
      return payload;
    },
  };
};

export const audioApiClient = createAudioApiClient();
export { normalizeDeviceSettings };
