import { shopAccessHeaders } from "../../shopAccessSession";

export const createApiRuntimePart1 = (runtime) => {
  const DEFAULT_API_BASE = process.env.VUE_APP_SHOP_API_BASE || "/api/shop";
  const DEFAULT_CAMPAIGN_ID = Number(process.env.VUE_APP_SHOP_CAMPAIGN_ID || 1);
  const DEFAULT_OWNER_CODE = String(
    process.env.VUE_APP_SHOP_OWNER_CODE || "BG1",
  ).toUpperCase();
  const DEFAULT_SOURCE_MODE = String(
    process.env.VUE_APP_SHOP_DATA_SOURCE ||
      (process.env.NODE_ENV === "test" || process.env.VITEST === "true"
        ? "demo"
        : "api"),
  )
    .trim()
    .toLowerCase();
  const DEFAULT_FALLBACK_ENABLED =
    String(process.env.VUE_APP_SHOP_API_FALLBACK || "false")
      .trim()
      .toLowerCase() !== "false";
  const hasWindow = () => typeof window !== "undefined";
  const normalizeApiBase = (value) => String(value || "").replace(/\/+$/, "");
  const resolveToken = () => {
    const explicit = String(process.env.VUE_APP_SHOP_API_TOKEN || "").trim();
    if (explicit) {
      return explicit;
    }
    if (!runtime.hasWindow()) {
      return "";
    }
    const tokenKeys = [
      "access_token",
      "blatyrpg.access_token",
      "blatyrpg.jwt",
      "blatyrpg.token",
      "auth_token",
      "token",
      "jwt",
    ];
    for (const key of tokenKeys) {
      const value = String(window.localStorage.getItem(key) || "").trim();
      if (value) {
        return value;
      }
    }
    return "";
  };
  const toErrorCode = (payload, fallback = "api_error") =>
    String(payload?.code || payload?.error || fallback);
  class ShopApiClientError extends Error {
    constructor(message, options = {}) {
      super(message);
      this.name = "ShopApiClientError";
      this.status = Number(options.status || 0);
      this.code = String(options.code || "api_error");
      this.payload = options.payload ?? null;
      this.network = options.network === true;
    }
  }
  const buildCampaignBaseUrl = (config = {}) => {
    const apiBase = runtime.normalizeApiBase(
      config.apiBase || runtime.DEFAULT_API_BASE,
    );
    const campaignId = Number(config.campaignId || runtime.DEFAULT_CAMPAIGN_ID);
    return `${apiBase}/campaigns/${campaignId}`;
  };
  const requestJson = async (url, options = {}) => {
    const token = runtime.resolveToken();
    const isFormData =
      typeof FormData !== "undefined" && options.body instanceof FormData;
    const headers = {
      Accept: "application/json",
      ...(options.body && !isFormData
        ? {
            "Content-Type": "application/json",
          }
        : {}),
      ...shopAccessHeaders(),
      ...(options.headers || {}),
    };
    if (token && !headers.Authorization) {
      headers.Authorization = `Bearer ${token}`;
    }
    try {
      const response = await fetch(url, {
        method: options.method || "GET",
        headers,
        body: options.body || undefined,
        credentials: options.credentials || "same-origin",
      });
      const raw = await response.text();
      let payload = null;
      if (raw) {
        try {
          payload = JSON.parse(raw);
        } catch (parseError) {
          if (!response.ok) {
            throw new runtime.ShopApiClientError(`http_${response.status}`, {
              status: response.status,
              code: `http_${response.status}`,
              payload: raw,
              network: false,
            });
          }
          throw parseError;
        }
      }
      if (!response.ok) {
        throw new runtime.ShopApiClientError(
          runtime.toErrorCode(payload, `http_${response.status}`),
          {
            status: response.status,
            code: runtime.toErrorCode(payload, `http_${response.status}`),
            payload,
            network: false,
          },
        );
      }
      return payload;
    } catch (error) {
      if (error instanceof runtime.ShopApiClientError) {
        throw error;
      }
      if (error instanceof SyntaxError) {
        throw new runtime.ShopApiClientError("invalid_json", {
          status: 0,
          code: "invalid_json",
          payload: null,
          network: false,
        });
      }
      throw new runtime.ShopApiClientError("network_error", {
        status: 0,
        code: "network_error",
        payload: null,
        network: true,
      });
    }
  };
  const isShopApiEnabled = () =>
    runtime.DEFAULT_SOURCE_MODE === "api" ||
    runtime.DEFAULT_SOURCE_MODE === "hybrid";
  const isShopApiFallbackEnabled = () =>
    runtime.DEFAULT_SOURCE_MODE === "hybrid" &&
    runtime.DEFAULT_FALLBACK_ENABLED;
  const isShopDemoMode = () => runtime.DEFAULT_SOURCE_MODE === "demo";
  const normalizeShopApiError = (error) => {
    if (error instanceof runtime.ShopApiClientError) {
      return {
        message: error.message,
        status: Number(error.status || 0),
        code: String(error.code || "api_error"),
        payload: error.payload ?? null,
        network: error.network === true,
      };
    }
    return {
      message: String(error?.message || "api_error"),
      status: Number(error?.status || 0),
      code: String(error?.code || "api_error"),
      payload: error?.payload ?? null,
      network: false,
    };
  };
  const isRecoverableShopApiError = (error) => {
    const normalized = runtime.normalizeShopApiError(error);
    return (
      normalized.network ||
      normalized.code === "invalid_json" ||
      normalized.status === 404 ||
      normalized.status === 405 ||
      normalized.status >= 500
    );
  };
  const createShopApiConfig = (overrides = {}) => ({
    apiBase: String(overrides.apiBase || runtime.DEFAULT_API_BASE),
    campaignId: Number(overrides.campaignId || runtime.DEFAULT_CAMPAIGN_ID),
    ownerCode: String(
      overrides.ownerCode || runtime.DEFAULT_OWNER_CODE,
    ).toUpperCase(),
    viewMode: String(overrides.viewMode || "").toLowerCase(),
  });
  return {
    DEFAULT_API_BASE,
    DEFAULT_CAMPAIGN_ID,
    DEFAULT_OWNER_CODE,
    DEFAULT_SOURCE_MODE,
    DEFAULT_FALLBACK_ENABLED,
    hasWindow,
    normalizeApiBase,
    resolveToken,
    toErrorCode,
    ShopApiClientError,
    buildCampaignBaseUrl,
    requestJson,
    isShopApiEnabled,
    isShopApiFallbackEnabled,
    isShopDemoMode,
    normalizeShopApiError,
    isRecoverableShopApiError,
    createShopApiConfig,
  };
};
