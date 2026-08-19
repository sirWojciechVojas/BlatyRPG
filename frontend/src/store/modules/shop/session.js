export const createSessionMutations = ({ touchTradeCacheState, gmModes }) => ({
  setApiStatus(state, status) {
    state.apiStatus = status;
  },
  setShopApiError(state, error = null) {
    state.apiError = error && typeof error === "object" ? { ...error } : null;
  },
  markTradeDataReady(state, cacheKey = "") {
    state.tradeDataLoaded = true;
    state.tradeDataCacheKey = String(cacheKey || "");
    state.tradeDataCacheVersion += 1;
    state.loadingBuy = false;
    state.loadingSell = false;
    state.errorBuy = "";
    state.errorSell = "";
  },
  touchTradeDataCache(state) {
    touchTradeCacheState(state);
  },
  setIsGM(state, value) {
    state.isGM = value === true && state.permissions?.isGm === true;
    touchTradeCacheState(state);
  },
  enterCharacterShoppingMode(state) {
    state.isGM = false;
    touchTradeCacheState(state);
  },
  setShopSession(state, payload = {}) {
    state.context = payload.context || null;
    state.actors = Array.isArray(payload.actors) ? payload.actors : [];
    state.permissions = {
      ...state.permissions,
      ...(payload.permissions || {}),
      ownerCodes: Array.isArray(payload.permissions?.ownerCodes)
        ? payload.permissions.ownerCodes
        : [],
    };
    if (!state.permissions.isGm) {
      state.isGM = false;
    }
    if (Number.isFinite(Number(payload.context?.campaignId))) {
      state.campaignId = Number(payload.context.campaignId);
    }
  },
  setCampaignId(state, campaignId) {
    if (Number.isFinite(Number(campaignId))) {
      state.campaignId = Number(campaignId);
    }
  },
  setGMMode(state, mode) {
    const allowed = new Set(Object.values(gmModes));
    state.gmMode = allowed.has(String(mode)) ? String(mode) : gmModes.TEMPLATES;
    touchTradeCacheState(state);
  },
});
