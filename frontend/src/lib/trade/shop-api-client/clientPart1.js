export const createApiClientPart1 = (runtime) => ({
  async getAccessOptions(config = {}) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/access/options`,
      {},
    );
  },
  async bootstrap(config = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/bootstrap?ownerCode=${encodeURIComponent(merged.ownerCode)}`;
    return runtime.requestJson(url, {
      ...(merged.viewMode
        ? { headers: { "X-Shop-View-Mode": merged.viewMode } }
        : {}),
    });
  },
  async createItemDictionaryEntry(config = {}, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/catalog/item-dictionaries`,
      {
        method: "POST",
        body: JSON.stringify(payload || {}),
      },
    );
  },
  async getIconMetadata(config = {}) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/catalog/icon-metadata`,
      {},
    );
  },
  async uploadIcon(config = {}, file) {
    const merged = runtime.createShopApiConfig(config);
    const body = new FormData();
    body.append("icon", file);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/catalog/icon-metadata`,
      { method: "POST", body },
    );
  },
  async uploadIconPair(config = {}, smallFile, largeFile) {
    const merged = runtime.createShopApiConfig(config);
    const body = new FormData();
    body.append("iconSmall", smallFile);
    body.append("iconLarge", largeFile);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/catalog/icon-metadata`,
      { method: "POST", body },
    );
  },
  async replaceIconImages(
    config = {},
    iconClass,
    smallFile,
    largeFile,
    metadata = {},
  ) {
    const merged = runtime.createShopApiConfig(config);
    const body = new FormData();
    body.append("iconSmall", smallFile);
    body.append("iconLarge", largeFile);
    Object.entries(metadata || {}).forEach(([key, value]) => {
      if (Array.isArray(value)) {
        value.forEach((entry) => body.append(`${key}[]`, entry));
      } else if (value !== undefined && value !== null) {
        body.append(key, value);
      }
    });
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/catalog/icon-metadata/${encodeURIComponent(iconClass)}/images`,
      { method: "POST", body },
    );
  },
  async saveIconMetadata(config = {}, iconClass, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/catalog/icon-metadata/${encodeURIComponent(iconClass)}`,
      { method: "PUT", body: JSON.stringify(payload || {}) },
    );
  },
  async deleteIconMetadata(config = {}, iconClass) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/catalog/icon-metadata/${encodeURIComponent(iconClass)}`,
      { method: "DELETE" },
    );
  },
  async updateItemDictionaryEntry(config = {}, entryId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/catalog/item-dictionaries/${Number(entryId)}`,
      {
        method: "PUT",
        body: JSON.stringify(payload || {}),
      },
    );
  },
  async deleteItemDictionaryEntry(config = {}, entryId) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/catalog/item-dictionaries/${Number(entryId)}`,
      {
        method: "DELETE",
      },
    );
  },
  async createShop(config = {}, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops`;
    return runtime.requestJson(url, {
      method: "POST",
      body: JSON.stringify(payload || {}),
    });
  },
  async updateShop(config = {}, shopId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}`;
    return runtime.requestJson(url, {
      method: "PATCH",
      body: JSON.stringify(payload || {}),
    });
  },
  async duplicateShop(config = {}, shopId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}/duplicate`;
    return runtime.requestJson(url, {
      method: "POST",
      body: JSON.stringify(payload || {}),
    });
  },
  async deleteShop(config = {}, shopId) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}`;
    return runtime.requestJson(url, {
      method: "DELETE",
    });
  },
  async updateShopActivation(config = {}, shopId, isActive = true) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}/activation`;
    return runtime.requestJson(url, {
      method: "PATCH",
      body: JSON.stringify({
        isActive: Boolean(isActive),
      }),
    });
  },
  async getShopProfile(config = {}, shopId) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}/profile`;
    return runtime.requestJson(url, {});
  },
  async saveShopProfile(config = {}, shopId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}/profile`;
    return runtime.requestJson(url, {
      method: "PUT",
      body: JSON.stringify(payload || {}),
    });
  },
  async previewShopPricing(config = {}, shopId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}/pricing/preview`;
    return runtime.requestJson(url, {
      method: "POST",
      body: JSON.stringify(payload || {}),
    });
  },
  async getShopProfileHistory(config = {}, shopId, limit = 30) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}/profile/history?limit=${Number(limit)}`;
    return runtime.requestJson(url, {});
  },
  async exportShopProfile(config = {}, shopId) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}/profile/export`;
    return runtime.requestJson(url, {});
  },
  async importShopProfile(config = {}, shopId, document = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}/profile/import`;
    return runtime.requestJson(url, {
      method: "POST",
      body: JSON.stringify(document || {}),
    });
  },
  async createTemplate(config = {}, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/templates`;
    return runtime.requestJson(url, {
      method: "POST",
      body: JSON.stringify(payload || {}),
    });
  },
  async updateTemplate(config = {}, templateId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/templates/${Number(templateId)}`;
    return runtime.requestJson(url, {
      method: "PUT",
      body: JSON.stringify(payload || {}),
    });
  },
  async deleteTemplate(config = {}, templateId) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/templates/${Number(templateId)}`;
    return runtime.requestJson(url, {
      method: "DELETE",
    });
  },
  async listTemplates(config = {}, filters = {}) {
    const merged = runtime.createShopApiConfig(config);
    const query = new URLSearchParams();
    Object.entries(filters || {}).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== "") {
        query.set(key, String(value));
      }
    });
    const suffix = query.toString() ? `?${query.toString()}` : "";
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/templates${suffix}`,
    );
  },
  async restoreTemplate(config = {}, templateId) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/templates/${Number(templateId)}/restore`,
      {
        method: "POST",
        body: JSON.stringify({}),
      },
    );
  },
  async duplicateTemplate(config = {}, templateId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/templates/${Number(templateId)}/duplicate`,
      {
        method: "POST",
        body: JSON.stringify(payload || {}),
      },
    );
  },
  async getContainers(config = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/containers?ownerCode=${encodeURIComponent(merged.ownerCode)}`;
    return runtime.requestJson(url);
  },
  async createItemInstance(config = {}, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/item-instances`,
      {
        method: "POST",
        body: JSON.stringify({
          ...payload,
          ownerCode: merged.ownerCode,
        }),
      },
    );
  },
  async moveContainerItems(config = {}, moves = []) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/containers/move`,
      {
        method: "POST",
        body: JSON.stringify({
          ownerCode: merged.ownerCode,
          moves,
        }),
      },
    );
  },
  async setContainerQuantities(config = {}, changes = []) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/containers/quantities`,
      {
        method: "PATCH",
        body: JSON.stringify({
          ownerCode: merged.ownerCode,
          changes,
        }),
      },
    );
  },
  async trashContainerItem(config = {}, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/containers/trash`,
      {
        method: "POST",
        body: JSON.stringify({
          ...payload,
          ownerCode: merged.ownerCode,
        }),
      },
    );
  },
  async restoreContainerItem(config = {}, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/containers/restore`,
      {
        method: "POST",
        body: JSON.stringify({
          ...payload,
          ownerCode: merged.ownerCode,
        }),
      },
    );
  },
});
