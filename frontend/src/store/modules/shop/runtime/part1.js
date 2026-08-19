import { OWNER_CODES } from "@/lib/trade/constants";
import i18n from "@/i18n";
import {
  isShopDemoMode,
  createShopApiConfig,
  isShopApiEnabled,
  isShopApiFallbackEnabled,
  normalizeShopApiError,
} from "@/lib/trade/shopApiClient";
import { normalizePersistedTradePayload } from "@/lib/trade/persistMigration";
import { getShopAccessSession } from "@/lib/trade/shopAccessSession";

export const SHOP_PERSIST_KEY = "blatyrpg-trade-shop-v1";

export const SHOP_DEFAULT_OWNER_CODE = String(
  process.env.VUE_APP_SHOP_OWNER_CODE || OWNER_CODES.BG1,
).toUpperCase();

export const hasWindow = () => typeof window !== "undefined";

export const t = (key, values = {}) => i18n.global.t(key, values);

export const SLOT_CODES = Object.freeze({
  STOISKO: "STOISKO",
});

export const resolveItemPlace = (entry, fallback = SLOT_CODES.STOISKO) =>
  String(entry?.ITEM_PLACE ?? entry?.SLOT ?? fallback);

export const defaultShopkeeperLabel = () =>
  t("shop.defaults.personalPseu.shopkeeper");

export const defaultSuggestionLabel = () =>
  t("shop.defaults.personalPseu.suggestion");

export const newShopNameWithId = (id) =>
  t("shop.defaults.shopName.newWithId", { id });

export const isDefaultPersonalPseu = (value) => {
  const normalized = String(value || "").trim();
  if (!normalized) {
    return true;
  }
  return new Set([defaultShopkeeperLabel(), defaultSuggestionLabel()]).has(
    normalized,
  );
};

export const loadPersistedTradeData = () => {
  if (!hasWindow() || !isShopDemoMode()) {
    return null;
  }
  try {
    const raw = window.localStorage.getItem(SHOP_PERSIST_KEY);
    if (!raw) {
      return null;
    }
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== "object") {
      return null;
    }
    return normalizePersistedTradePayload(parsed);
  } catch (error) {
    return null;
  }
};

export const persistTradeData = (payload) => {
  if (!hasWindow() || !isShopDemoMode()) {
    return;
  }
  try {
    window.localStorage.setItem(SHOP_PERSIST_KEY, JSON.stringify(payload));
  } catch (error) {
    // ignore storage errors
  }
};

export const resolveOwnerCode = (state = {}, explicitOwnerCode = "") => {
  const developmentSession = getShopAccessSession();
  if (developmentSession?.ownerCode) {
    return developmentSession.ownerCode;
  }
  const permissionOwnerCodes = (state?.permissions?.ownerCodes || [])
    .map((ownerCode) =>
      String(ownerCode || "")
        .trim()
        .toUpperCase(),
    )
    .filter(Boolean);
  const contextOwnerCode = String(state?.context?.ownerCode || "")
    .trim()
    .toUpperCase();
  const sessionOwnerCode =
    permissionOwnerCodes.length && !state?.permissions?.isGm
      ? permissionOwnerCodes.includes(contextOwnerCode)
        ? contextOwnerCode
        : permissionOwnerCodes[0]
      : contextOwnerCode;
  const owner = String(
    explicitOwnerCode || sessionOwnerCode || SHOP_DEFAULT_OWNER_CODE,
  )
    .trim()
    .toUpperCase();
  return owner || SHOP_DEFAULT_OWNER_CODE;
};

export const resolveShopApiConfig = (state = {}, options = {}) =>
  createShopApiConfig({
    ownerCode: resolveOwnerCode(state, options.ownerCode),
    viewMode: options.viewMode || (state.isGM ? "management" : "character"),
    campaignId: Number(
      options.campaignId ||
        state.campaignId ||
        process.env.VUE_APP_SHOP_CAMPAIGN_ID ||
        1,
    ),
  });

export const shouldUseShopApi = () => isShopApiEnabled();

export const shouldAllowShopMockFallback = () => isShopApiFallbackEnabled();

export const isShopApiAuthorizationError = (error) => {
  const normalized = normalizeShopApiError(error);
  const code = String(normalized.code || "").toLowerCase();
  return (
    normalized.status === 401 ||
    normalized.status === 403 ||
    code.includes("token") ||
    code.includes("forbidden") ||
    code.includes("unauthorized") ||
    code.includes("brak tokena")
  );
};

export const buildTradeIdempotencyKey = (prefix = "trade") =>
  `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;

export const cloneItem = (item) => JSON.parse(JSON.stringify(item));

export let tradingDataLoadPromise = null;

export const yieldToUiThread = () =>
  new Promise((resolve) => {
    if (typeof window === "undefined") {
      setTimeout(resolve, 0);
      return;
    }
    const run = () => setTimeout(resolve, 0);
    if (typeof window.requestAnimationFrame === "function") {
      window.requestAnimationFrame(run);
      return;
    }
    run();
  });

export const tradeDataCacheKey = (state = {}, options = {}) => {
  const ownerCode = resolveOwnerCode(state, options.ownerCode);
  const rawShopId = options.shopId ?? state.activeShopId;
  const shopId =
    rawShopId === null || rawShopId === undefined || rawShopId === ""
      ? Number.NaN
      : Number(rawShopId);
  const campaignId = String(
    options.campaignId ||
      state.campaignId ||
      process.env.VUE_APP_SHOP_CAMPAIGN_ID ||
      "default",
  );
  const actorMode = state.isGM ? `gm:${state.gmMode}` : "player";
  return [
    campaignId,
    ownerCode,
    Number.isFinite(shopId) ? shopId : "none",
    actorMode,
  ].join("|");
};

export const touchTradeCacheState = (state) => {
  if (!state.tradeDataLoaded) {
    return;
  }
  state.tradeDataCacheKey = tradeDataCacheKey(state);
  state.tradeDataCacheVersion += 1;
};

export const findById = (items, id) => {
  if (id === null || id === undefined || id === "") {
    return undefined;
  }
  return items.find((item) => Number(item.ID) === Number(id));
};

export const toNonNegativeNumber = (value, fallback = 0) => {
  const parsed = Number(value);
  if (!Number.isFinite(parsed)) {
    return fallback;
  }
  return Math.max(0, parsed);
};
