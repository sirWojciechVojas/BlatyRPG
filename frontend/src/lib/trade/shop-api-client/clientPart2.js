export const createApiClientPart2 = (runtime) => ({
  async mergeContainerItems(config = {}, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/containers/merge`,
      {
        method: "POST",
        body: JSON.stringify({
          ...payload,
          ownerCode: merged.ownerCode,
        }),
      },
    );
  },
  async updateItemInstance(config = {}, instanceId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/item-instances/${Number(instanceId)}`,
      {
        method: "PATCH",
        body: JSON.stringify({
          ...payload,
          ownerCode: merged.ownerCode,
        }),
      },
    );
  },
  async generateSuggestions(config = {}, shopId) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}/suggestions/generate`;
    return runtime.requestJson(url, {
      method: "POST",
      body: JSON.stringify({}),
    });
  },
  async promoteSuggestions(config = {}, shopId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}/suggestions/promote`;
    return runtime.requestJson(url, {
      method: "POST",
      body: JSON.stringify(payload || {}),
    });
  },
  async applySuggestions(config = {}, shopId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}/suggestions/apply`;
    return runtime.requestJson(url, {
      method: "POST",
      body: JSON.stringify(payload || {}),
    });
  },
  async materializeSuggestion(config = {}, shopId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}/suggestions/materialize`;
    return runtime.requestJson(url, {
      method: "POST",
      body: JSON.stringify(payload || {}),
    });
  },
  async replaceAssortment(config = {}, shopId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}/assortment/replace`;
    return runtime.requestJson(url, {
      method: "POST",
      body: JSON.stringify(payload || {}),
    });
  },
  async transferAssortment(config = {}, shopId, moves = []) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}/assortment/transfer`;
    return runtime.requestJson(url, {
      method: "POST",
      body: JSON.stringify({
        ownerCode: merged.ownerCode,
        moves,
      }),
    });
  },
  async rollAssortment(config = {}, shopId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/shops/${Number(shopId)}/assortment/roll`;
    return runtime.requestJson(url, {
      method: "POST",
      body: JSON.stringify(payload || {}),
    });
  },
  async tradeBuy(config = {}, payload = {}, idempotencyKey = "") {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/trade/buy`;
    const headers = idempotencyKey
      ? {
          "Idempotency-Key": String(idempotencyKey),
        }
      : {};
    return runtime.requestJson(url, {
      method: "POST",
      headers,
      body: JSON.stringify(payload || {}),
    });
  },
  async quoteTradeBuyPayment(config = {}, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/trade/buy/quote`;
    return runtime.requestJson(url, {
      method: "POST",
      body: JSON.stringify(payload || {}),
    });
  },
  async tradeSell(config = {}, payload = {}, idempotencyKey = "") {
    const merged = runtime.createShopApiConfig(config);
    const url = `${runtime.buildCampaignBaseUrl(merged)}/trade/sell`;
    const headers = idempotencyKey
      ? {
          "Idempotency-Key": String(idempotencyKey),
        }
      : {};
    return runtime.requestJson(url, {
      method: "POST",
      headers,
      body: JSON.stringify(payload || {}),
    });
  },
  async listTradeLedger(config = {}, filters = {}) {
    const merged = runtime.createShopApiConfig(config);
    const query = new URLSearchParams();
    Object.entries(filters || {}).forEach(([key, value]) => {
      if (value === null || value === undefined || value === "") {
        return;
      }
      query.set(key, String(value));
    });
    const suffix = query.toString() ? `?${query.toString()}` : "";
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/trade/ledger${suffix}`,
    );
  },
  async reverseTradeLedger(config = {}, transactionId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/trade/ledger/${Number(transactionId)}/reverse`,
      {
        method: "POST",
        body: JSON.stringify(payload || {}),
      },
    );
  },
  async redoTradeLedger(config = {}, transactionId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/trade/ledger/${Number(transactionId)}/redo`,
      {
        method: "POST",
        body: JSON.stringify(payload || {}),
      },
    );
  },
  async correctTradeLedger(config = {}, transactionId, payload = {}) {
    const merged = runtime.createShopApiConfig(config);
    return runtime.requestJson(
      `${runtime.buildCampaignBaseUrl(merged)}/trade/ledger/${Number(transactionId)}/correct`,
      {
        method: "POST",
        body: JSON.stringify(payload || {}),
      },
    );
  },
});
