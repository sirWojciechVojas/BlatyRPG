import {
  JsonApiError,
  jsonApiClient,
  resolveAccessToken,
} from "@/lib/api/jsonApiClient";

const segment = (value, name) => {
  if (value === null || value === undefined || value === "") {
    throw new TypeError(`${name}_required`);
  }
  return encodeURIComponent(String(value));
};

const apiUrl = (path, baseUrl) =>
  `${String(baseUrl || process.env.VUE_APP_API_BASE || "/api").replace(/\/+$/u, "")}/${String(path).replace(/^\/+/u, "")}`;

const errorCode = (payload, fallback) =>
  payload?.code || payload?.error || payload?.message || fallback;

const campaignBase = (campaignId) =>
  `/campaigns/${segment(campaignId, "campaign_id")}/handouts`;

export const createHandoutApiClient = (options = {}) => {
  const client = options.client || jsonApiClient;
  const getFetch = () => options.fetchImpl || window.fetch.bind(window);
  const tokenResolver = options.tokenResolver || resolveAccessToken;
  const baseUrl = options.baseUrl;

  const request = (path, requestOptions = {}) => {
    const { query, ...rest } = requestOptions;
    if (!query || typeof query !== "object") return client.request(path, rest);
    const params = new URLSearchParams();
    Object.entries(query).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== "") {
        params.set(key, String(value));
      }
    });
    const suffix = params.toString();
    return client.request(suffix ? `${path}?${suffix}` : path, rest);
  };

  const multipart = async (path, file) => {
    if (!(file instanceof Blob)) throw new TypeError("handout_file_required");
    const formData = new FormData();
    formData.append("file", file, file.name || "handout-asset");
    let response;
    try {
      const token = tokenResolver();
      response = await getFetch()(apiUrl(path, baseUrl), {
        method: "POST",
        headers: {
          Accept: "application/json",
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: formData,
        credentials: "same-origin",
      });
    } catch (cause) {
      throw new JsonApiError("network_error", {
        network: true,
        payload: cause,
      });
    }
    const raw = await response.text();
    let payload = null;
    try {
      payload = raw ? JSON.parse(raw) : null;
    } catch (_error) {
      throw new JsonApiError("invalid_json", {
        status: response.status,
        payload: raw,
      });
    }
    if (!response.ok) {
      throw new JsonApiError(errorCode(payload, `http_${response.status}`), {
        status: response.status,
        payload,
      });
    }
    return payload;
  };

  const blob = async (path) => {
    let response;
    try {
      const token = tokenResolver();
      response = await getFetch()(apiUrl(path, baseUrl), {
        headers: { ...(token ? { Authorization: `Bearer ${token}` } : {}) },
        credentials: "same-origin",
      });
    } catch (cause) {
      throw new JsonApiError("network_error", {
        network: true,
        payload: cause,
      });
    }
    if (!response.ok) {
      let payload = null;
      try {
        payload = await response.json();
      } catch (_error) {
        // The API may return a non-JSON error from a reverse proxy.
      }
      throw new JsonApiError(errorCode(payload, `http_${response.status}`), {
        status: response.status,
        payload,
      });
    }
    return response.blob();
  };

  return {
    listLibrary(query = {}) {
      return request("/handout-library", { query });
    },
    getLibraryEntry(entryId) {
      return request(
        `/handout-library/entries/${segment(entryId, "entry_id")}`,
      );
    },
    createLibraryEntry(draft) {
      return request("/handout-library/entries", {
        method: "POST",
        body: draft,
      });
    },
    updateLibraryEntry(entryId, changes) {
      return request(
        `/handout-library/entries/${segment(entryId, "entry_id")}`,
        { method: "PATCH", body: changes },
      );
    },
    deleteLibraryEntry(entryId, revision) {
      return request(
        `/handout-library/entries/${segment(entryId, "entry_id")}`,
        { method: "DELETE", body: { revision } },
      );
    },
    restoreLibraryEntry(entryId) {
      return request(
        `/handout-library/entries/${segment(entryId, "entry_id")}/restore`,
        { method: "POST" },
      );
    },
    createFolder(draft) {
      return request("/handout-library/folders", {
        method: "POST",
        body: draft,
      });
    },
    updateFolder(folderId, changes) {
      return request(
        `/handout-library/folders/${segment(folderId, "folder_id")}`,
        { method: "PATCH", body: changes },
      );
    },
    deleteFolder(folderId) {
      return request(
        `/handout-library/folders/${segment(folderId, "folder_id")}`,
        { method: "DELETE" },
      );
    },
    createTag(draft) {
      return request("/handout-library/tags", { method: "POST", body: draft });
    },
    deleteTag(tagId) {
      return request(`/handout-library/tags/${segment(tagId, "tag_id")}`, {
        method: "DELETE",
      });
    },
    uploadAsset(file) {
      return multipart("/handout-library/assets", file);
    },
    fetchAssetBlob(assetId) {
      return blob(`/handout-assets/${segment(assetId, "asset_id")}/file`);
    },
    listCampaign(campaignId, query = {}) {
      return request(campaignBase(campaignId), { query });
    },
    getCampaignHandout(campaignId, handoutId) {
      return request(
        `${campaignBase(campaignId)}/${segment(handoutId, "handout_id")}`,
      );
    },
    publish(campaignId, libraryEntryId) {
      return request(`${campaignBase(campaignId)}/publish`, {
        method: "POST",
        body: { libraryEntryId },
      });
    },
    updateCampaignHandout(campaignId, handoutId, changes) {
      return request(
        `${campaignBase(campaignId)}/${segment(handoutId, "handout_id")}`,
        { method: "PATCH", body: changes },
      );
    },
    deleteCampaignHandout(campaignId, handoutId, revision) {
      return request(
        `${campaignBase(campaignId)}/${segment(handoutId, "handout_id")}`,
        { method: "DELETE", body: { revision } },
      );
    },
    restoreCampaignHandout(campaignId, handoutId) {
      return request(
        `${campaignBase(campaignId)}/${segment(handoutId, "handout_id")}/restore`,
        { method: "POST" },
      );
    },
    shareCampaignHandout(campaignId, handoutId, changes) {
      return request(
        `${campaignBase(campaignId)}/${segment(handoutId, "handout_id")}/share`,
        { method: "POST", body: changes },
      );
    },
    transferCampaignHandoutAuthor(campaignId, handoutId, changes) {
      return request(
        `${campaignBase(campaignId)}/${segment(handoutId, "handout_id")}/transfer-author`,
        { method: "POST", body: changes },
      );
    },
    listNotifications(campaignId) {
      return request(
        `/campaigns/${segment(campaignId, "campaign_id")}/handout-notifications`,
      );
    },
    markNotificationRead(campaignId, notificationId) {
      return request(
        `/campaigns/${segment(campaignId, "campaign_id")}/handout-notifications/${segment(notificationId, "notification_id")}`,
        { method: "PATCH" },
      );
    },
  };
};

export const handoutApiClient = createHandoutApiClient();
