import {
  JsonApiError,
  jsonApiClient,
  resolveAccessToken,
} from "@/lib/api/jsonApiClient";

const segment = (value, name) => {
  const result = Number(value);
  if (!Number.isInteger(result) || result < 1)
    throw new TypeError(`${name}_required`);
  return result;
};

const queryPath = (path, query = {}) => {
  const params = new URLSearchParams();
  Object.entries(query).forEach(([key, value]) => {
    if (
      value !== null &&
      value !== undefined &&
      value !== "" &&
      value !== false
    ) {
      params.set(key, String(value));
    }
  });
  const suffix = params.toString();
  return suffix ? `${path}?${suffix}` : path;
};

const apiUrl = (path, baseUrl) =>
  `${String(baseUrl || process.env.VUE_APP_API_BASE || "/api").replace(/\/+$/u, "")}/${String(path).replace(/^\/+/u, "")}`;

export const COMPENDIUM_PAGE_SIZE = 25;

export const createCompendiumApiClient = (options = {}) => {
  const client = options.client || jsonApiClient;
  const readCache = new Map();
  const configuredReadCacheTtlMs = Number(options.readCacheTtlMs ?? 60000);
  const readCacheTtlMs = Number.isFinite(configuredReadCacheTtlMs)
    ? Math.max(0, configuredReadCacheTtlMs)
    : 60000;
  const maxReadCacheEntries = 120;
  let cacheGeneration = 0;

  const clearCache = () => {
    cacheGeneration += 1;
    readCache.clear();
  };

  const cacheRead = (key, value) => {
    readCache.delete(key);
    readCache.set(key, value);
    while (readCache.size > maxReadCacheEntries) {
      readCache.delete(readCache.keys().next().value);
    }
  };

  const request = (path, requestOptions = {}) => {
    const { query, invalidateCache = true, ...rest } = requestOptions;
    const target = queryPath(path, query);
    const method = String(rest.method || "GET").toUpperCase();
    if (method !== "GET" || readCacheTtlMs === 0) {
      if (method === "GET") return client.request(target, rest);
      if (invalidateCache) clearCache();
      const write = Promise.resolve(client.request(target, rest));
      return invalidateCache ? write.finally(clearCache) : write;
    }

    const token = String((options.tokenResolver || resolveAccessToken)() || "");
    const key = `${token}\n${target}`;
    const cached = readCache.get(key);
    if (cached?.promise) return cached.promise;
    if (cached && cached.expiresAt > Date.now()) {
      return Promise.resolve(cached.value);
    }
    if (cached) readCache.delete(key);

    const generation = cacheGeneration;
    const promise = Promise.resolve()
      .then(() => client.request(target, rest))
      .then(
        (value) => {
          if (generation === cacheGeneration) {
            cacheRead(key, {
              value,
              expiresAt: Date.now() + readCacheTtlMs,
            });
          }
          return value;
        },
        (error) => {
          if (readCache.get(key)?.promise === promise) readCache.delete(key);
          throw error;
        },
      );
    cacheRead(key, { promise, expiresAt: 0 });
    return promise;
  };
  const campaignBase = (campaignId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/compendium`;
  const worldBase = (universeId) =>
    `/universes/${segment(universeId, "universe_id")}/compendium`;

  const authorizedFetch = async (path, fetchOptions = {}) => {
    const fetchImpl = options.fetchImpl || window.fetch.bind(window);
    const token = (options.tokenResolver || resolveAccessToken)();
    let response;
    try {
      response = await fetchImpl(apiUrl(path, options.baseUrl), {
        ...fetchOptions,
        headers: {
          Accept: "application/json",
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
          ...(fetchOptions.headers || {}),
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
        // A proxy may return a non-JSON error.
      }
      throw new JsonApiError(payload?.code || `http_${response.status}`, {
        status: response.status,
        payload,
      });
    }
    return response;
  };

  return {
    clearCache,
    preloadCampaign(campaignId) {
      return Promise.allSettled([
        request(campaignBase(campaignId)),
        request(`${campaignBase(campaignId)}/entries`, {
          query: {
            status: "active",
            page: 1,
            limit: COMPENDIUM_PAGE_SIZE,
          },
        }),
      ]);
    },
    campaignOverview(campaignId) {
      return request(campaignBase(campaignId));
    },
    campaignEntries(campaignId, query = {}) {
      return request(`${campaignBase(campaignId)}/entries`, { query });
    },
    campaignTimeline(campaignId, query = {}) {
      return request(`${campaignBase(campaignId)}/timeline`, { query });
    },
    campaignEntry(campaignId, entryId) {
      return request(
        `${campaignBase(campaignId)}/entries/${segment(entryId, "entry_id")}`,
      );
    },
    recordRead(campaignId, entryId) {
      return request(
        `${campaignBase(campaignId)}/entries/${segment(entryId, "entry_id")}/read`,
        { method: "POST", body: {}, invalidateCache: false },
      );
    },
    favorite(campaignId, entryId, favorite) {
      return request(
        `${campaignBase(campaignId)}/entries/${segment(entryId, "entry_id")}/favorite`,
        { method: "PUT", body: { favorite: Boolean(favorite) } },
      );
    },
    reveal(campaignId, entryId, sectionKeys = null, userId = null) {
      return request(
        `${campaignBase(campaignId)}/entries/${segment(entryId, "entry_id")}/reveal`,
        { method: "POST", body: { sectionKeys, userId } },
      );
    },
    revokeReveal(campaignId, entryId) {
      return request(
        `${campaignBase(campaignId)}/entries/${segment(entryId, "entry_id")}/reveal`,
        { method: "DELETE" },
      );
    },
    pin(campaignId, entryId) {
      return request(
        `${campaignBase(campaignId)}/entries/${segment(entryId, "entry_id")}/pin`,
        { method: "POST", body: {} },
      );
    },
    addNote(campaignId, entryId, body, visibility = "gm") {
      return request(
        `${campaignBase(campaignId)}/entries/${segment(entryId, "entry_id")}/notes`,
        { method: "POST", body: { body, visibility } },
      );
    },
    materialize(campaignId, entryId, payload) {
      return request(
        `${campaignBase(campaignId)}/entries/${segment(entryId, "entry_id")}/materialize`,
        {
          method: "POST",
          body: payload,
        },
      );
    },
    worldOverview(universeId) {
      return request(worldBase(universeId));
    },
    worldEntries(universeId, query = {}) {
      return request(`${worldBase(universeId)}/entries`, { query });
    },
    worldEntry(universeId, entryId) {
      return request(
        `${worldBase(universeId)}/entries/${segment(entryId, "entry_id")}`,
      );
    },
    createEntry(universeId, payload) {
      return request(`${worldBase(universeId)}/entries`, {
        method: "POST",
        body: payload,
      });
    },
    updateEntry(universeId, entryId, payload) {
      return request(
        `${worldBase(universeId)}/entries/${segment(entryId, "entry_id")}`,
        {
          method: "PATCH",
          body: payload,
        },
      );
    },
    publish(universeId, entryId, revision) {
      return request(
        `${worldBase(universeId)}/entries/${segment(entryId, "entry_id")}/publish`,
        {
          method: "POST",
          body: { revision },
        },
      );
    },
    restoreVersion(universeId, entryId, versionId, revision) {
      return request(
        `${worldBase(universeId)}/entries/${segment(entryId, "entry_id")}/versions/${segment(versionId, "version_id")}/restore`,
        {
          method: "POST",
          body: { revision },
        },
      );
    },
    archive(universeId, entryId, revision) {
      return request(
        `${worldBase(universeId)}/entries/${segment(entryId, "entry_id")}`,
        {
          method: "DELETE",
          body: { revision },
        },
      );
    },
    restore(universeId, entryId, revision) {
      return request(
        `${worldBase(universeId)}/entries/${segment(entryId, "entry_id")}/restore`,
        {
          method: "POST",
          body: { revision },
        },
      );
    },
    updateCalendar(universeId, payload) {
      return request(`${worldBase(universeId)}/calendar`, {
        method: "PUT",
        body: payload,
      });
    },
    createType(universeId, payload) {
      return request(`${worldBase(universeId)}/types`, {
        method: "POST",
        body: payload,
      });
    },
    updateType(universeId, typeId, payload) {
      return request(
        `${worldBase(universeId)}/types/${segment(typeId, "type_id")}`,
        { method: "PATCH", body: payload },
      );
    },
    deleteType(universeId, typeId) {
      return request(
        `${worldBase(universeId)}/types/${segment(typeId, "type_id")}`,
        { method: "DELETE" },
      );
    },
    createTag(universeId, payload) {
      return request(`${worldBase(universeId)}/tags`, {
        method: "POST",
        body: payload,
      });
    },
    deleteTag(universeId, tagId) {
      return request(
        `${worldBase(universeId)}/tags/${segment(tagId, "tag_id")}`,
        { method: "DELETE" },
      );
    },
    addEditor(universeId, identity) {
      return request(`${worldBase(universeId)}/editors`, {
        method: "POST",
        body: { identity },
      });
    },
    removeEditor(universeId, userId) {
      return request(
        `${worldBase(universeId)}/editors/${segment(userId, "user_id")}`,
        { method: "DELETE" },
      );
    },
    assignOwner(universeId, userId) {
      return request(`${worldBase(universeId)}/owner`, {
        method: "PUT",
        body: { userId },
      });
    },
    async uploadAsset(universeId, file) {
      if (!(file instanceof Blob))
        throw new TypeError("compendium_file_required");
      clearCache();
      const formData = new FormData();
      formData.append("file", file, file.name || "compendium-asset");
      const response = await authorizedFetch(
        `${worldBase(universeId)}/assets`,
        {
          method: "POST",
          body: formData,
        },
      );
      const result = await response.json();
      clearCache();
      return result;
    },
    async uploadCorpusAsset(universeId, assetId, file) {
      if (!(file instanceof Blob))
        throw new TypeError("compendium_file_required");
      clearCache();
      const formData = new FormData();
      formData.append("file", file, file.name || "compendium-source-asset");
      const response = await authorizedFetch(
        `${worldBase(universeId)}/corpus-assets/${segment(assetId, "asset_id")}/file`,
        { method: "POST", body: formData },
      );
      const result = await response.json();
      clearCache();
      return result;
    },
    listAssets(universeId) {
      return request(`${worldBase(universeId)}/assets`);
    },
    deleteAsset(universeId, assetId) {
      return request(
        `${worldBase(universeId)}/assets/${segment(assetId, "asset_id")}`,
        { method: "DELETE" },
      );
    },
    async fetchAssetBlob(assetId, campaignId = null) {
      const path = queryPath(
        `/compendium-assets/${segment(assetId, "asset_id")}/file`,
        {
          campaignId,
        },
      );
      const response = await authorizedFetch(path);
      return response.blob();
    },
    async fetchCorpusAssetBlob(
      assetId,
      { campaignId = null, characterId = null, universeId = null } = {},
    ) {
      const path = queryPath(
        `/compendium-corpus-assets/${segment(assetId, "asset_id")}/file`,
        { campaignId, characterId, universeId },
      );
      const response = await authorizedFetch(path);
      return response.blob();
    },
  };
};

export const compendiumApiClient = createCompendiumApiClient();
