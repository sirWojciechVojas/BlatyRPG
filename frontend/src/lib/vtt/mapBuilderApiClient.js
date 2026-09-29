import {
  JsonApiError,
  jsonApiClient,
  resolveAccessToken,
} from "@/lib/api/jsonApiClient";

const segment = (value, name) => {
  if (value === null || value === undefined || value === "")
    throw new TypeError(`${name}_required`);
  return encodeURIComponent(String(value));
};

const apiUrl = (path) =>
  `${String(process.env.VUE_APP_API_BASE || "/api").replace(/\/+$/u, "")}/${String(path).replace(/^\/+/, "")}`;

const authenticatedUpload = async (path, formData, method = "POST") => {
  let response;
  try {
    const token = resolveAccessToken();
    response = await window.fetch(apiUrl(path), {
      method,
      body: formData,
      headers: token ? { Authorization: `Bearer ${token}` } : {},
      credentials: "same-origin",
    });
  } catch (cause) {
    throw new JsonApiError("network_error", { network: true, payload: cause });
  }
  const payload = await response.json().catch(() => null);
  if (!response.ok) {
    throw new JsonApiError(payload?.code || `http_${response.status}`, {
      status: response.status,
      payload,
    });
  }
  return payload;
};

export const createMapBuilderApiClient = (client = jsonApiClient) => {
  const base = (campaignId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/maps`;
  const map = (campaignId, mapId) =>
    `${base(campaignId)}/${segment(mapId, "map_id")}`;
  return {
    list(campaignId, options = {}) {
      return client.request(base(campaignId), options);
    },
    get(campaignId, mapId, options = {}) {
      return client.request(map(campaignId, mapId), options);
    },
    create(campaignId, payload) {
      return client.request(base(campaignId), {
        method: "POST",
        body: payload,
      });
    },
    save(campaignId, mapId, payload) {
      return client.request(map(campaignId, mapId), {
        method: "PUT",
        body: payload,
      });
    },
    acquireLock(campaignId, mapId, editorId) {
      return client.request(`${map(campaignId, mapId)}/lock`, {
        method: "POST",
        body: { editorId },
      });
    },
    releaseLock(campaignId, mapId, editorId) {
      return client.request(`${map(campaignId, mapId)}/lock`, {
        method: "DELETE",
        body: { editorId },
      });
    },
    revisions(campaignId, mapId) {
      return client.request(`${map(campaignId, mapId)}/revisions`);
    },
    restore(campaignId, mapId, revisionId, editorId) {
      return client.request(
        `${map(campaignId, mapId)}/revisions/${segment(revisionId, "revision_id")}/restore`,
        {
          method: "POST",
          body: { editorId },
        },
      );
    },
    publish(campaignId, mapId, payload) {
      return client.request(`${map(campaignId, mapId)}/publish`, {
        method: "POST",
        body: payload,
      });
    },
    aiStatus(campaignId) {
      return client.request(`${base(campaignId)}/ai/status`);
    },
    createAiJob(campaignId, mapId, payload) {
      return client.request(`${map(campaignId, mapId)}/ai-jobs`, {
        method: "POST",
        body: payload,
      });
    },
    aiJob(campaignId, mapId, jobId) {
      return client.request(
        `${map(campaignId, mapId)}/ai-jobs/${segment(jobId, "job_id")}`,
      );
    },
    cancelAiJob(campaignId, mapId, jobId) {
      return client.request(
        `${map(campaignId, mapId)}/ai-jobs/${segment(jobId, "job_id")}`,
        {
          method: "DELETE",
          body: {},
        },
      );
    },
    assets(campaignId) {
      return client.request(`${base(campaignId)}/assets`);
    },
    uploadAsset(campaignId, file, metadata = {}) {
      const data = new FormData();
      data.append("file", file, file.name || "map-asset");
      data.append("metadata", JSON.stringify(metadata));
      return authenticatedUpload(`${base(campaignId)}/assets`, data);
    },
    importPackage(campaignId, file) {
      const data = new FormData();
      data.append("file", file, file.name || "asset-package.zip");
      return authenticatedUpload(`${base(campaignId)}/asset-packages`, data);
    },
    uploadRender(campaignId, mapId, blob, format = "webp") {
      const data = new FormData();
      data.append("file", blob, `map-${mapId}.${format}`);
      return authenticatedUpload(`${map(campaignId, mapId)}/render`, data);
    },
  };
};

export const mapBuilderApiClient = createMapBuilderApiClient();
