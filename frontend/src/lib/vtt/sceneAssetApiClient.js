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
  `${String(baseUrl || process.env.VUE_APP_API_BASE || "/api").replace(/\/+$/u, "")}/${String(path).replace(/^\/+/, "")}`;

const errorCode = (payload, fallback) =>
  payload?.code || payload?.error || payload?.message || fallback;

export const sceneAssetLocation = (value) => {
  const match = String(value || "").match(
    /(?:^|\/api)\/campaigns\/(\d+)\/scene-assets\/([^/]+)\/file(?:[?#].*)?$/u,
  );
  return match
    ? { campaignId: Number(match[1]), assetKey: decodeURIComponent(match[2]) }
    : null;
};

export const createSceneAssetApiClient = (options = {}) => {
  const client = options.client || jsonApiClient;
  const getFetch = () => options.fetchImpl || window.fetch.bind(window);
  const tokenResolver = options.tokenResolver || resolveAccessToken;
  const baseUrl = options.baseUrl;
  const base = (campaignId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/scene-assets`;

  const authenticatedFetch = async (path, requestOptions = {}) => {
    let response;
    try {
      const token = tokenResolver();
      response = await getFetch()(apiUrl(path, baseUrl), {
        ...requestOptions,
        headers: {
          Accept: requestOptions.accept || "application/json",
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
          ...(requestOptions.headers || {}),
        },
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
        // A reverse proxy may return a non-JSON error document.
      }
      throw new JsonApiError(errorCode(payload, `http_${response.status}`), {
        status: response.status,
        payload,
      });
    }
    return response;
  };

  return {
    async list(campaignId) {
      const payload = await client.request(base(campaignId));
      return Array.isArray(payload?.items) ? payload.items : [];
    },
    async upload(campaignId, file) {
      if (!(file instanceof Blob)) throw new TypeError("scene_asset_required");
      const formData = new FormData();
      formData.append("file", file, file.name || "scene-background");
      const response = await authenticatedFetch(base(campaignId), {
        method: "POST",
        body: formData,
      });
      return response.json();
    },
    async fetchBlob(campaignId, assetKey) {
      const response = await authenticatedFetch(
        `${base(campaignId)}/${segment(assetKey, "asset_key")}/file`,
        { accept: "image/*" },
      );
      return response.blob();
    },
    async fetchBlobFromUrl(url) {
      const location = sceneAssetLocation(url);
      if (!location) throw new TypeError("scene_asset_url_required");
      return this.fetchBlob(location.campaignId, location.assetKey);
    },
  };
};

export const sceneAssetApiClient = createSceneAssetApiClient();
